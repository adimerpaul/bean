/**
 * Ventas sin conexión.
 *
 * Todo vive en localStorage: el catálogo (copia de los productos para poder cobrar
 * con el servidor caído) y la cola de ventas pendientes de enviar.
 *
 * Cada venta nace con un uuid propio. Ese uuid viaja al backend, que lo tiene con
 * índice único: si el envío se repite (se cortó la respuesta, se volvió a
 * tocar "Enviar", otra pestaña con la misma cola), el servidor devuelve la venta
 * que ya guardó en lugar de crear otra. Por eso reenviar siempre es seguro.
 */

const CATALOGO = 'catalogoOfflineBean'
const VENTAS = 'ventasOfflineBean'

export const PENDIENTE = 'PENDIENTE'
export const ENVIADA = 'ENVIADA'
export const ERROR = 'ERROR'

/** Campos que necesita la pantalla de venta; el resto no se guarda para no llenar localStorage. */
const CAMPOS_PRODUCTO = ['id', 'codigo', 'codigo_barras', 'nombre', 'categoria', 'categoria_id', 'unidad', 'precio_venta', 'stock_inicial', 'foto']

function leer (key, fallback) {
  try {
    const raw = localStorage.getItem(key)

    return raw ? JSON.parse(raw) : fallback
  } catch {
    return fallback
  }
}

function escribir (key, value) {
  try {
    localStorage.setItem(key, JSON.stringify(value))

    return true
  } catch {
    return false
  }
}

export function nuevoUuid () {
  if (crypto?.randomUUID) return crypto.randomUUID()
  // Navegadores viejos o sin https: mismo formato v4 armado a mano.
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
    const r = Math.random() * 16 | 0

    return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16)
  })
}

/* ---------------------------------- catálogo --------------------------------- */

export function guardarCatalogo (productos, categorias) {
  const limpios = (productos || []).map(p => Object.fromEntries(CAMPOS_PRODUCTO.map(k => [k, p[k]])))
  const guardado = escribir(CATALOGO, { productos: limpios, categorias: categorias || [], fecha: Date.now() })

  return guardado ? limpios.length : 0
}

export function catalogoOffline () {
  return leer(CATALOGO, { productos: [], categorias: [], fecha: 0 })
}

function descontarDelCatalogo (detalles) {
  const catalogo = catalogoOffline()
  if (!catalogo.productos.length) return
  const vendidos = {}
  ;(detalles || []).forEach(d => { vendidos[d.producto_id] = (vendidos[d.producto_id] || 0) + Number(d.cantidad || 0) })
  catalogo.productos.forEach(p => {
    if (vendidos[p.id]) p.stock_inicial = Number(Math.max(0, Number(p.stock_inicial || 0) - vendidos[p.id]).toFixed(3))
  })
  escribir(CATALOGO, catalogo)
}

export function hayCatalogo () {
  return catalogoOffline().productos.length > 0
}

/* -------------------------- ventas guardadas en el equipo -------------------- */

/**
 * `exportado` dice si la venta ya se convirtió en una venta real del sistema.
 * Es el campo que manda: `estado` sólo distingue si el último intento falló.
 */
export function ventasOffline () {
  const ventas = leer(VENTAS, [])
  if (!Array.isArray(ventas)) return []

  return ventas.map(v => ({ ...v, exportado: v.exportado ?? v.estado === ENVIADA }))
}

export function guardarVentaOffline (venta) {
  const ventas = ventasOffline()
  ventas.unshift(venta)
  if (!escribir(VENTAS, ventas)) throw new Error('No hay espacio en el dispositivo para guardar la venta')

  return venta
}

export function actualizarVentaOffline (uuid, cambios) {
  const ventas = ventasOffline().map(v => (v.uuid === uuid ? { ...v, ...cambios } : v))
  escribir(VENTAS, ventas)

  return ventas
}

export function eliminarVentaOffline (uuid) {
  const ventas = ventasOffline().filter(v => v.uuid !== uuid)
  escribir(VENTAS, ventas)

  return ventas
}

/** Marca la venta como ya convertida en venta del sistema. */
export function marcarExportada (uuid, { numero, venta_id: ventaId, duplicada = false }) {
  // Al dejar de estar pendiente ya no reserva stock: se descuenta del catálogo guardado
  // para que no "reaparezca" hasta la próxima descarga. Las duplicadas se saltan porque
  // el servidor pudo registrarlas antes de bajar el catálogo.
  const venta = ventasOffline().find(v => v.uuid === uuid)
  if (venta && !venta.exportado && !duplicada) descontarDelCatalogo(venta.detalles)

  return actualizarVentaOffline(uuid, {
    exportado: true,
    exportado_en: new Date().toISOString(),
    estado: ENVIADA,
    numero,
    venta_id: ventaId,
    error: null,
    duplicada,
  })
}

/** Saca de la lista las que ya se registraron en el servidor. */
export function limpiarExportadas () {
  const ventas = ventasOffline().filter(v => !v.exportado)
  escribir(VENTAS, ventas)

  return ventas
}

export function ventasPorEnviar () {
  return ventasOffline().filter(v => !v.exportado)
}

/**
 * Stock que queda disponible sin conexión: el del catálogo menos lo que ya se
 * cobró en ventas que todavía no se enviaron (el servidor aún no las descontó).
 */
export function stockComprometido () {
  const reservado = {}
  ventasPorEnviar().forEach(venta => {
    (venta.detalles || []).forEach(item => {
      reservado[item.producto_id] = Number(((reservado[item.producto_id] || 0) + Number(item.cantidad || 0)).toFixed(3))
    })
  })

  return reservado
}

/**
 * Arma la venta tal como quedará guardada en el dispositivo. El descuento del
 * encabezado se reparte entre las líneas igual que en el backend (el resto va a la
 * última) para que el ticket impreso sin conexión coincida con el que se registre.
 *
 * @param items  [{ id, codigo, nombre, unidad, foto, cantidad, precio_venta }]
 */
export function armarVentaOffline ({ items, descuento = 0, tipoPago = 'EFECTIVO', efectivo = 0, qr = 0, observacion = null, usuario = '', uuid = null, caja = 1 }) {
  const dos = value => Number(Number(value || 0).toFixed(2))
  const base = items.reduce((sum, i) => sum + Number(i.precio_venta) * Number(i.cantidad), 0)
  const total = dos(Math.max(0, base - descuento))
  let repartido = 0

  const detalles = items.map((item, index) => {
    const subtotal = dos(Number(item.precio_venta) * Number(item.cantidad))
    const suDescuento = index === items.length - 1 ? dos(descuento - repartido) : dos(descuento * (base ? subtotal / base : 0))
    repartido = dos(repartido + suDescuento)

    return {
      producto_id: item.id,
      codigo: item.codigo,
      nombre: item.nombre,
      unidad: item.unidad,
      foto: item.foto,
      cantidad: Number(item.cantidad),
      precio_venta: Number(item.precio_venta),
      subtotal,
      descuento: suDescuento,
      total: dos(subtotal - suDescuento),
    }
  })

  return {
    // Si la venta ya se intentó enviar online, se conserva su uuid: al reintentar,
    // el servidor reconoce la que quizá sí guardó y no la registra dos veces.
    uuid: uuid || nuevoUuid(),
    numero_local: `OFF-${String(Date.now()).slice(-8)}`,
    fecha: new Date().toISOString(),
    usuario_nombre: usuario,
    subtotal: dos(base),
    descuento: dos(descuento),
    total,
    tipo_pago: tipoPago,
    monto_efectivo: dos(efectivo),
    monto_qr: dos(qr),
    observacion: observacion || null,
    caja: Number(caja) || 1,
    estado: PENDIENTE,
    exportado: false,
    exportado_en: null,
    error: null,
    duplicada: false,
    detalles,
  }
}

/** Cuerpo que espera POST /ventas. */
export function cuerpoParaEnviar (venta) {
  return {
    uuid: venta.uuid,
    fecha_offline: venta.fecha,
    descuento: venta.descuento,
    tipo_pago: venta.tipo_pago,
    monto_efectivo: venta.monto_efectivo,
    monto_qr: venta.monto_qr,
    observacion: venta.observacion,
    caja: venta.caja || 1,
    detalles: (venta.detalles || []).map(i => ({
      producto_id: i.producto_id,
      cantidad: i.cantidad,
      precio_venta: i.precio_venta,
    })),
  }
}
