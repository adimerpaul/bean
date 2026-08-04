# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Qué es

Bean: sistema de ventas e inventario para un negocio de pollos, embutidos, carnes, quesos y congelados (Bolivia, moneda Bs). Dos aplicaciones separadas en un solo repo:

- `back/` — API REST Laravel 13 (PHP 8.3), Sanctum + spatie/laravel-permission.
- `front/` — SPA/PWA Quasar 2 + Vue 3 (Vite), que consume la API por axios.

La UI, los nombres de tablas/columnas y los mensajes de error están en español; el código PHP/JS (variables, métodos) está en inglés. Mantener esa mezcla.

## Comandos

```bash
# Backend
cd back
composer install
php artisan migrate         # crea esquema + datos iniciales (ver "Datos iniciales")
php artisan serve           # http://localhost:8000
composer run dev            # serve + queue:listen + pail + vite en paralelo
composer run test           # config:clear + artisan test
php artisan test --filter=test_admin_can_login_and_receive_a_sanctum_token
vendor/bin/pint             # formateo (Laravel Pint)

# Frontend
cd front
npm install
npm run dev                 # quasar dev (abre navegador)
npm run build               # quasar build → front/dist
```

No hay tests en el frontend (`npm test` es un no-op).

Los tests corren sobre SQLite en memoria (`back/phpunit.xml`) y usan `RefreshDatabase`, por lo que **las migraciones se ejecutan completas en cada test** — incluidas las que insertan datos. Una migración que rompa en SQLite rompe toda la suite.

## Configuración de entorno

- Backend: `back/.env` (MySQL por defecto). `DB_DATABASE` en `.env.example` apunta a `mundolac`; ajustar al crear el entorno.
- Frontend: `front/.env.development` y `front/.env.production` definen `VITE_API_BACK` (ej. `http://localhost:8000/api`) y `VITE_VERSION`. No hay valores hardcodeados de API salvo el `api` de ejemplo sin usar en `boot/axios.js`.

## Datos iniciales: viven en migraciones, no en seeders

`DatabaseSeeder` está vacío a propósito. Todo el bootstrap de datos ocurre dentro de migraciones:

- `2026_07_28_060000_load_initial_inventory.php` — crea permisos base y el usuario `admin` / `admin` con todos los permisos.
- `2026_07_28_100000_create_bean_test_catalog.php` — borra y recrea el catálogo de prueba: 10 categorías × 10 productos (`BEAN-####`). `BeanCatalogTest` verifica esos 100/10 exactos.
- Las migraciones posteriores registran sus propios permisos (`Ver/Crear/Anular Ventas`, `Ver/Crear Compras`, `Gestionar Configuración`) y se los dan al `admin`.

Al agregar una funcionalidad con permisos nuevos, seguir ese patrón: `Permission::firstOrCreate(...)` + `givePermissionTo` al admin dentro de la migración de la feature.

## Autorización

No se usan roles ni middleware `can:`. Cada controlador tiene un método privado `authorizeAction(Request, string $permission)` que hace `abort_unless($request->user()?->hasPermissionTo($permission), 403, ...)` y se llama **al inicio de cada acción**. Los permisos son strings en español con mayúsculas ("Ver Ventas"). Los permisos se asignan directamente al usuario (`model_has_permissions`), no vía roles.

En el frontend el mismo string controla el menú y los botones: `proxy.$store.hasPermission('Ver Ventas')`. La lista de ítems del menú vive en `front/src/layouts/MainLayout.vue` (`links[].can`).

La tabla `permissions` tiene dos columnas propias del proyecto: **`grupo`** (default `Otros`) y **`orden`**. Con eso el formulario de `usuarios/IndexPage.vue` arma las cajas de permisos leyendo lo que devuelve `GET /permissions` — no hay lista de grupos en el frontend. **Todo permiso nuevo debe fijar `grupo` y `orden` en su migración**; si se omite cae en "Otros", visible pero fuera de lugar. Un permiso que no se renderiza es peligroso: `PUT /users/{id}/permissions` reemplaza la lista completa con lo marcado en pantalla, así que al guardar un usuario se pierde lo que no se dibujó.

## Inventario: stock + lotes FIFO por vencimiento

`productos.stock_inicial` es el stock **actual** (nombre heredado), `decimal(12,3)` para soportar productos por peso. Además existe `lotes` con `cantidad_disponible` por lote y fecha de vencimiento. Los dos se mueven en paralelo y hay que mantenerlos consistentes:

- **Compra** (`CompraController@store`): crea un `Lote` por cada detalle, incrementa `stock_inicial` y actualiza `precio_compra` del producto al último precio pagado.
- **Venta** (`VentaController@store`): valida stock con `lockForUpdate`, decrementa `stock_inicial` y consume lotes en orden **FIFO por `fecha_vencimiento`** (los `NULL` al final), registrando la asignación en la tabla pivote `venta_detalle_lotes`.
- **Anulación de venta**: devuelve stock y repone exactamente los lotes registrados en `venta_detalle_lotes`.
- **Anulación de compra**: falla con 422 si el stock ya se consumió; si no, descuenta y borra los lotes de esa compra.
- **Almacén** (`AlmacenController@apply`): es un conteo físico — al aplicarlo el `stock_inicial` **pasa a ser** la cantidad contada (no se suma). Ver "Almacén" más abajo.
- **Baja** (`BajaController@store`): igual que una venta pero sin cobro — descuenta `stock_inicial` y consume lotes (el lote elegido primero si se envía `lote_id`, si no FIFO), guardando la asignación en `baja_detalle_lotes`. Su anulación repone stock y lotes. El costo se valora con `precio_compra` del producto, no con precio de venta.

Todo esto va dentro de `DB::transaction` con `Producto::lockForUpdate()`. Cualquier operación nueva que toque existencias debe hacer lo mismo.

Los detalles de venta/compra son **snapshots**: copian `codigo`, `nombre`, `unidad`, `foto`, `precio_compra` del producto en el momento de la operación. Los reportes y la ganancia se calculan sobre esos snapshots, no sobre `productos`. No reemplazar por joins a `productos`.

### Almacén: revisión física del stock (conteo)

`almacenes` + `almacen_detalles` son el único documento **editable** del sistema y no representan un ingreso de mercadería sino una **revisión del stock real de la tienda**: lo contado se vuelve el stock oficial. Estados: `BORRADOR` (en revisión) `→ APLICADO | ANULADO`.

- **Se llena entre varias personas a la vez**, cada una desde su celular. Por eso no hay un "guardar todo": cada producto se persiste solo con `POST/PUT/DELETE /almacenes/{id}/detalles[/{detalle}]`, y hay un **unique `(almacen_id, producto_id)`** — un producto se cuenta una sola vez. Si otro ya lo contó, `storeDetalle` responde **409** con quién y cuánto; el frontend ofrece reemplazar (`reemplazar: true`) y la línea pasa a nombre de quien corrige. Las páginas refrescan solas (10 s llenando, 15 s en avance).
- `POST /almacenes/{id}/aplicar` es el botón **"Actualizar productos"**: por cada línea `stock_inicial` pasa a ser la cantidad contada, y se guardan `stock_anterior`, `stock_nuevo` y `diferencia` en el detalle. **Los productos que nadie contó no se tocan.**
- **Un producto puede contarse en varios lotes**: `almacen_detalle_conteos` guarda una fila por lote (lote + `fecha_vencimiento` + cantidad) y `almacen_detalles.cantidad` pasa a ser su suma. Al reabrir un producto ya contado se recupera tal cual (cantidad, lotes y vencimientos). Guardar una línea **reemplaza** sus conteos (`syncConteos`).
- Los lotes se ajustan según cómo se contó:
  - **Con lotes cargados** (`replaceLots`): lo contado es la verdad — se guarda el saldo de los lotes vigentes en `almacen_detalle_lotes`, se ponen en cero (no se borran, hay `venta_detalle_lotes` apuntando) y se crea un `Lote` por cada lote contado.
  - **Sólo cantidad total**: se ajusta por la **diferencia** — sobrante → `Lote` nuevo por esa diferencia; faltante → consume lotes FIFO, registrando también en `almacen_detalle_lotes`.
- `PUT /almacenes/{id}/anular` deshace el ajuste (aplica `-diferencia`), borra el lote creado o repone los consumidos; falla con 422 si ese stock ya se vendió o se dio de baja.
- No se pide costo al contar: `precio_compra` es snapshot del producto y sólo sirve para valorizar la diferencia en los resúmenes.
- Por esto `lotes.compra_detalle_id` es **nullable** y existe `lotes.almacen_detalle_id`: un lote puede nacer de una compra o de un almacén. Ambos aparecen igual en `/vencimientos` (páginas "Por vencer" y "Vencidos").
- Permisos (grupo `Almacén`): `Ver/Crear/Editar/Aplicar/Anular Almacenes`. `Aplicar` está separado a propósito: quien cuenta no es necesariamente quien autoriza el ajuste.
- Frontend, tres páginas: `almacenes/IndexPage.vue` (listado + diálogo de creación), `almacenes/LlenarPage.vue` (`/almacenes/:id`, contar) y `almacenes/AvancePage.vue` (`/almacenes/:id/avance`, control y botón de aplicar).
- `tests/Feature/AlmacenTest.php` cubre conteo → aplicación (sobrante y faltante) → anulación, el 409 por producto duplicado, el bloqueo al aplicar y los permisos.

### Productos por peso

`unidad === 'KG'` marca producto pesado: cantidades con 3 decimales, precio con 4. En `ventas/NuevaPage.vue` hay soporte de **etiquetas de balanza EAN-13**: códigos de 13 dígitos que empiezan con `2`, donde los dígitos 1–7 son el código de producto y 8–12 el peso en gramos, validados con dígito verificador EAN-13.

## Ventas: pagos y descuentos

`tipo_pago` ∈ `EFECTIVO | QR | COMBINADO`. El backend exige que `monto_efectivo + monto_qr == total` (tolerancia 0.009); para EFECTIVO/QR puros los deriva del total. El descuento de cabecera se **prorratea por línea** proporcional al subtotal, dando la diferencia acumulada a la última línea para que la suma cuadre al centavo. `estado` ∈ `COMPLETADA | ANULADA`; los resúmenes y el dashboard filtran siempre por `COMPLETADA` y respetan `deleted_at` en consultas con `DB::table` (soft deletes manuales).

Los números de documento se generan **después** del insert: `V-00000001` / `C-00000001` a partir del id.

## Auditoría y soft deletes

`Producto`, `Venta`, `User` (y demás modelos de negocio) usan `owen-it/laravel-auditing` + `SoftDeletes`. Las consultas crudas con `DB::table('venta_detalles')` deben añadir `whereNull('deleted_at')` explícitamente — ya se hace en `VentaController@dashboard`; mantener ese cuidado.

## Imágenes

Se guardan como archivos en `back/public/images/**` (no en `storage/`), convertidas a WebP con la extensión GD (`imagewebp`, calidad ~88) por los controladores de productos, usuarios y configuración. La columna guarda la ruta relativa (ej. `productos/x.webp`). El frontend las arma con `proxy.$imgBase + '/images/' + ruta`, donde `$imgBase` es `VITE_API_BACK` sin el sufijo `/api`.

## Frontend: convenciones

- **Options-API-style vía proxy**: casi todas las páginas usan `<script setup>` + `const { proxy } = getCurrentInstance()` y luego `proxy.$axios`, `proxy.$alert`, `proxy.$store`, `proxy.$imgBase`, `proxy.$empresa`. Esas propiedades globales se registran en `front/src/boot/axios.js`. Seguir ese patrón en páginas nuevas en lugar de importar axios directamente.
- **Estado**: un único store Pinia en `src/stores/example-store.js` (`useCounterStore`) con `isLogged`, `user`, `permissions` y el getter `hasPermission`. El nombre del archivo es heredado del scaffold; no crear stores paralelos sin necesidad.
- **Sesión**: token en `localStorage.tokenBean`, permisos cacheados en `permissionsBean`, datos de empresa en `empresaBean`. El guard en `src/router/index.js` sólo comprueba la existencia del token; el 401 de `/me` limpia todo y redirige a `/login`.
- **Notificaciones y diálogos**: siempre `proxy.$alert` (`Alert.success/error/warning/info/dialog/confirm` en `src/addons/Alert.js`), no `Notify`/`Dialog` directos.
- **Impresión**: tickets de 80 mm generados con `printd` en `src/addons/ventaPrint.js` y `compraPrint.js`, usando los datos de empresa de `localStorage` vía `src/addons/empresa.js`.
- **Mayúsculas**: directiva global `v-uppercase` (`src/boot/uppercase.js`) para inputs que deben ir en mayúsculas.
- **Estilo de código**: el JS de las páginas está escrito muy denso (varias declaraciones por línea, funciones en una sola línea, CSS scoped minificado). Al editar una página existente, respetar su densidad en lugar de reformatearla.
- Errores de API: se muestran leyendo `e.response?.data?.errors` (primer mensaje) y cayendo a `e.response?.data?.message`.

## Rutas API

Todas en `back/routes/api.php`, planas (sin `apiResource`), con dos endpoints públicos: `POST /api/login` y `GET /api/configuracion` (la SPA la necesita antes de autenticarse para pintar logo/nombre de empresa). El resto va bajo `auth:sanctum`. Los reportes tienen endpoints dedicados `*-exportar/excel` (maatwebsite/excel) y `*-exportar/pdf` (dompdf + vistas Blade en `back/resources/views/{ventas,productos}/reporte.blade.php`).
