<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm">
      <div><div class="text-subtitle1 text-weight-bold">Ventas offline</div><div class="text-caption text-grey-7">Ventas cobradas sin internet, guardadas en este dispositivo</div></div><q-space/>
      <q-chip dense :color="offline?'negative':'positive'" text-color="white" :icon="offline?'wifi_off':'wifi'" :label="offline?'Sin conexión':'Con conexión'" class="q-mr-xs"/>
      <q-btn v-if="puedeVender" dense unelevated color="primary" icon="point_of_sale" label="Nueva venta offline" no-caps to="/ventas/offline/nueva"/>
    </div>

    <div class="row q-col-gutter-sm q-mb-sm">
      <div v-for="card in cards" :key="card.label" class="col-6 col-md-3"><q-card flat bordered :class="`summary-card bg-${card.color}-1 text-${card.color}-9`"><q-card-section class="row items-center q-pa-sm"><q-avatar :color="card.color" text-color="white" :icon="card.icon" size="36px"/><div class="q-ml-sm"><div class="text-caption">{{card.label}}</div><div class="text-h6 text-weight-bold">{{card.money?'Bs ':''}}{{card.money?money(card.value):card.value}}</div></div></q-card-section></q-card></div>
    </div>

    <q-card flat bordered>
      <q-card-section class="row items-center q-col-gutter-sm q-pa-sm">
        <q-input v-model="search" dense outlined clearable debounce="300" placeholder="Buscar número, usuario o producto" class="col-12 col-md-4"><template #prepend><q-icon name="search"/></template></q-input>
        <q-select v-model="filtroExportado" :options="opcionesExportado" dense outlined emit-value map-options label="Exportado a ventas" class="col-12 col-md-3"/>
        <q-space/>
        <div class="col-auto row q-gutter-xs">
          <q-btn dense flat no-caps color="primary" icon="fact_check" label="Verificar" :disable="!porExportar.length||sincronizando" @click="verificarManual"><q-tooltip>Pregunta al servidor si alguna ya está registrada, para no cobrarla dos veces</q-tooltip></q-btn>
          <q-btn dense unelevated color="positive" no-caps icon="cloud_upload" :label="`Exportar a ventas (${porExportar.length})`" :disable="!porExportar.length" :loading="sincronizando" @click="exportarTodas"/>
          <q-btn v-if="exportadas.length" dense flat no-caps icon="cleaning_services" label="Quitar exportadas" @click="quitarExportadas"/>
        </div>
      </q-card-section>

      <q-table dense flat :rows="filtradas" :columns="columns" row-key="uuid" :rows-per-page-options="[15,30,50,100,0]" no-data-label="No hay ventas offline guardadas">
        <template #body-cell-exportado="p">
          <q-td :props="p">
            <q-badge :color="p.row.exportado?'positive':'orange-8'" :label="p.row.exportado?'SÍ':'NO'"/>
            <q-tooltip v-if="p.row.exportado">Exportada el {{formatDate(p.row.exportado_en)}} como venta {{p.row.numero}}</q-tooltip>
          </q-td>
        </template>
        <template #body-cell-numero="p">
          <q-td :props="p"><div class="text-weight-bold">{{p.row.numero_local}}</div><router-link v-if="p.row.numero" :to="enlaceVenta(p.row)" class="text-caption text-positive venta-link">{{p.row.numero}}<q-icon name="open_in_new" size="12px"/></router-link></q-td>
        </template>
        <template #body-cell-total="p"><q-td :props="p"><b>Bs {{money(p.value)}}</b></q-td></template>
        <template #body-cell-tipo_pago="p"><q-td :props="p"><q-chip dense square :color="paymentColor(p.value)" text-color="white" :icon="p.value==='QR'?'qr_code_2':p.value==='EFECTIVO'?'payments':'account_balance_wallet'">{{p.value}}</q-chip></q-td></template>
        <template #body-cell-detalles="p">
          <q-td :props="p"><div class="items-list"><div v-for="d in p.row.detalles" :key="d.producto_id" class="items-row"><b>{{qty(d.cantidad)}}{{d.unidad==='KG'?'kg':''}}</b> {{d.nombre}}</div><q-tooltip><div v-for="d in p.row.detalles" :key="d.producto_id">{{qty(d.cantidad)}}{{d.unidad==='KG'?'kg':''}} · {{d.nombre}}</div></q-tooltip></div></q-td>
        </template>
        <template #body-cell-estado="p">
          <q-td :props="p">
            <q-badge :color="estadoColor(p.row)" :label="p.row.exportado?'EXPORTADA':p.row.estado"/>
            <q-tooltip v-if="p.row.error" class="bg-negative">{{p.row.error}}</q-tooltip>
            <div v-if="p.row.duplicada" class="text-caption text-orange-9">ya existía</div>
          </q-td>
        </template>
        <template #body-cell-actions="p">
          <q-td :props="p">
            <q-btn-dropdown dense flat color="primary" icon="more_vert" dropdown-icon="none">
              <q-list dense style="min-width:180px">
                <q-item clickable v-close-popup @click="verDetalle(p.row)"><q-item-section avatar><q-icon name="visibility" color="primary"/></q-item-section><q-item-section>Ver detalle</q-item-section></q-item>
                <q-item v-if="p.row.numero" clickable v-close-popup :to="enlaceVenta(p.row)"><q-item-section avatar><q-icon name="link" color="positive"/></q-item-section><q-item-section>Ver venta {{p.row.numero}}</q-item-section></q-item>
                <q-item clickable v-close-popup @click="imprimir(p.row)"><q-item-section avatar><q-icon name="print" color="blue-grey"/></q-item-section><q-item-section>Imprimir</q-item-section></q-item>
                <q-separator/>
                <q-item v-if="!p.row.exportado" clickable v-close-popup class="text-positive" @click="exportarUna(p.row)"><q-item-section avatar><q-icon name="cloud_upload"/></q-item-section><q-item-section>Exportar a ventas</q-item-section></q-item>
                <q-item clickable v-close-popup class="text-negative" @click="eliminar(p.row)"><q-item-section avatar><q-icon name="delete"/></q-item-section><q-item-section>Eliminar</q-item-section></q-item>
              </q-list>
            </q-btn-dropdown>
          </q-td>
        </template>
      </q-table>
    </q-card>

    <q-dialog v-model="dialog"><q-card style="width:760px;max-width:96vw">
      <q-card-section class="row items-center q-py-sm">
        <div><div class="text-subtitle1 text-weight-bold">{{selected.numero_local}}<router-link v-if="selected.numero" :to="enlaceVenta(selected)" class="text-positive venta-link"> · venta {{selected.numero}}</router-link></div><div class="text-caption">{{formatDate(selected.fecha)}} · {{selected.usuario_nombre}} · Caja {{selected.caja||1}}</div></div>
        <q-space/><q-btn flat round dense icon="print" color="primary" @click="imprimir(selected)"/><q-badge :color="selected.exportado?'positive':'orange-8'" :label="selected.exportado?'EXPORTADA':'NO EXPORTADA'"/><q-btn flat round dense icon="close" v-close-popup/>
      </q-card-section>
      <q-separator/>
      <q-table dense flat :rows="selected.detalles||[]" :columns="detailColumns" row-key="producto_id" hide-pagination :rows-per-page-options="[0]"><template #body-cell-total="p"><q-td :props="p">Bs {{money(p.value)}}</q-td></template></q-table>
      <q-separator/>
      <q-card-section class="q-pa-sm">
        <div class="row"><span>Subtotal</span><q-space/>Bs {{money(selected.subtotal)}}</div>
        <div class="row text-negative"><span>Descuento</span><q-space/>- Bs {{money(selected.descuento)}}</div>
        <div class="row"><span>Efectivo</span><q-space/>Bs {{money(selected.monto_efectivo)}}</div>
        <div class="row"><span>QR</span><q-space/>Bs {{money(selected.monto_qr)}}</div>
        <div class="row text-h6 text-primary"><b>Total</b><q-space/><b>Bs {{money(selected.total)}}</b></div>
        <div v-if="selected.observacion" class="text-caption q-mt-xs">Observación: {{selected.observacion}}</div>
        <div v-if="selected.error" class="text-caption text-negative q-mt-xs">Último error: {{selected.error}}</div>
        <div class="text-caption text-grey-6 q-mt-xs">uuid {{selected.uuid}}</div>
      </q-card-section>
    </q-card></q-dialog>
  </q-page>
</template>

<script setup>
import {computed,getCurrentInstance,reactive,ref} from 'vue'
import {printSale} from '../../addons/ventaPrint'
import {ERROR,actualizarVentaOffline,cuerpoParaEnviar,eliminarVentaOffline,limpiarExportadas,marcarExportada,ventasOffline} from '../../addons/ventasOffline'
const {proxy}=getCurrentInstance()
const ventas=ref(ventasOffline()),search=ref(''),filtroExportado=ref('todas'),sincronizando=ref(false),dialog=ref(false),selected=reactive({})
const opcionesExportado=[{label:'Todas',value:'todas'},{label:'No exportadas',value:'no'},{label:'Exportadas',value:'si'}]
const money=v=>Number(v||0).toFixed(2),qty=v=>{const n=Number(v||0);return Number.isInteger(n)?String(n):String(parseFloat(n.toFixed(3)))}
const puedeVender=computed(()=>proxy.$store.hasPermission(['Crear Ventas Offline','Crear Ventas']))
const offline=computed(()=>proxy.$store.offline||!navigator.onLine)
const formatDate=v=>v?new Date(v).toLocaleString('es-BO'):''
const paymentColor=v=>v==='EFECTIVO'?'green':v==='QR'?'blue':'purple'
const estadoColor=venta=>venta.exportado?'positive':venta.estado===ERROR?'negative':'orange'

const porExportar=computed(()=>ventas.value.filter(v=>!v.exportado))
const exportadas=computed(()=>ventas.value.filter(v=>v.exportado))
const montoPorExportar=computed(()=>porExportar.value.reduce((sum,v)=>sum+Number(v.total||0),0))
const cards=computed(()=>[
  {label:'Por exportar',value:porExportar.value.length,money:false,icon:'cloud_queue',color:'orange'},
  {label:'Monto por exportar',value:montoPorExportar.value,money:true,icon:'pending_actions',color:'primary'},
  {label:'Exportadas',value:exportadas.value.length,money:false,icon:'cloud_done',color:'green'},
  {label:'Con error',value:ventas.value.filter(v=>!v.exportado&&v.estado===ERROR).length,money:false,icon:'error',color:'red'},
])
const filtradas=computed(()=>{const term=String(search.value||'').trim().toUpperCase()
  return ventas.value.filter(v=>{
    if(filtroExportado.value==='si'&&!v.exportado)return false
    if(filtroExportado.value==='no'&&v.exportado)return false
    if(!term)return true
    return String(v.numero_local||'').toUpperCase().includes(term)||String(v.numero||'').toUpperCase().includes(term)||String(v.usuario_nombre||'').toUpperCase().includes(term)||v.detalles.some(d=>String(d.nombre||'').toUpperCase().includes(term))
  })})

const columns=[
  {name:'actions',label:'',align:'left'},
  {name:'exportado',label:'Exportado',field:'exportado',align:'center',sortable:true},
  {name:'numero',label:'Nº',field:'numero_local',align:'left',sortable:true},
  {name:'fecha',label:'Fecha del cobro',field:r=>formatDate(r.fecha),align:'left',sortable:true},
  {name:'usuario',label:'Usuario',field:'usuario_nombre',align:'left'},
  {name:'caja',label:'Caja',field:r=>`Caja ${r.caja||1}`,align:'center'},
  {name:'tipo_pago',label:'Pago',field:'tipo_pago',align:'center'},
  {name:'detalles',label:'Productos',field:'detalles',align:'left'},
  {name:'descuento',label:'Descuento',field:'descuento',format:v=>`Bs ${money(v)}`,align:'right'},
  {name:'total',label:'Total',field:'total',align:'right',sortable:true},
  {name:'estado',label:'Estado',field:'estado',align:'center'},
]
const detailColumns=[{name:'codigo',label:'Código',field:'codigo',align:'left'},{name:'nombre',label:'Producto',field:'nombre',align:'left'},{name:'cantidad',label:'Cant.',field:r=>`${qty(r.cantidad)} ${r.unidad}`,align:'center'},{name:'precio',label:'Precio',field:'precio_venta',format:v=>`Bs ${money(v)}`,align:'right'},{name:'total',label:'Total',field:'total',align:'right'}]

// Vínculo con la venta registrada en el sistema (se busca por su número en Ventas).
const enlaceVenta=venta=>({path:'/ventas',query:{q:venta.numero}})
function verDetalle(row){Object.assign(selected,row);dialog.value=true}
function imprimir(venta){printSale({...venta,numero:venta.numero||venta.numero_local,estado:venta.exportado?'COMPLETADA':'PENDIENTE',pendiente:!venta.exportado})}

/* ------------------------------ exportar a ventas ---------------------------- */
/**
 * Antes de exportar se le pregunta al servidor por los uuid guardados: si alguno ya
 * está registrado (la respuesta anterior se perdió) se marca como exportado en vez
 * de volver a cobrarlo. El POST además es idempotente por uuid, así que ni un envío
 * repetido crea dos ventas.
 */
async function verificarDuplicados(avisar=true){const pendientes=porExportar.value;if(!pendientes.length)return 0
  try{const {data}=await proxy.$axios.post('/ventas-offline/verificar',{uuids:pendientes.map(v=>v.uuid)});const registradas=data.registradas||{};let encontradas=0
    Object.entries(registradas).forEach(([uuid,venta])=>{encontradas++;ventas.value=marcarExportada(uuid,{numero:venta.numero,venta_id:venta.id,duplicada:true})})
    if(avisar)proxy.$alert[encontradas?'info':'success'](encontradas?`${encontradas} venta(s) ya estaban registradas`:'Ninguna venta de la lista está en el servidor todavía',encontradas?'Se marcaron como exportadas para no duplicarlas':'')
    return encontradas}
  catch(e){if(avisar)proxy.$alert.error(e.response?.data?.message||'No se pudo verificar. ¿Hay conexión?');throw e}}

function verificarManual(){verificarDuplicados().catch(()=>{/* el aviso ya se mostró */})}

function exportarTodas(){proxy.$alert.dialog(`¿Exportar ${porExportar.value.length} venta(s) a ventas?`,'Se registrarán en el sistema con la fecha y hora en que se cobraron.').onOk(async()=>{
  sincronizando.value=true;let exportadasOk=0,fallidas=0
  try{await verificarDuplicados(false)}catch{sincronizando.value=false;return proxy.$alert.error('Sin conexión con el servidor. Intenta más tarde')}
  for(const venta of porExportar.value){const resultado=await exportarVenta(venta)
    if(resultado==='SIN_CONEXION'){sincronizando.value=false;proxy.$alert.error('Se perdió la conexión','Las ventas restantes siguen guardadas; vuelve a exportar cuando haya internet');return resumen(exportadasOk,fallidas)}
    resultado==='OK'?exportadasOk++:fallidas++}
  sincronizando.value=false;resumen(exportadasOk,fallidas)})}

function resumen(ok,fallidas){if(ok&&!fallidas)proxy.$alert.success(`${ok} venta(s) registradas en ventas`)
  else if(ok)proxy.$alert.info(`${ok} exportadas · ${fallidas} con error`,'Revisa las que quedaron en rojo')
  else if(fallidas)proxy.$alert.error(`${fallidas} venta(s) no se pudieron registrar`,'Revisa el detalle de cada una')}

async function exportarUna(venta){sincronizando.value=true;const resultado=await exportarVenta(venta);sincronizando.value=false
  if(resultado==='OK')proxy.$alert.success('Venta registrada en ventas')
  else if(resultado==='SIN_CONEXION')proxy.$alert.error('Sin conexión con el servidor','La venta sigue guardada en el dispositivo')
  else proxy.$alert.error(ventas.value.find(v=>v.uuid===venta.uuid)?.error||'No se pudo registrar')}

/** Devuelve 'OK' | 'ERROR' | 'SIN_CONEXION'. Nunca lanza: la lista no se pierde. */
async function exportarVenta(venta){if(venta.exportado)return 'OK'
  try{const {data}=await proxy.$axios.post('/ventas',cuerpoParaEnviar(venta));ventas.value=marcarExportada(venta.uuid,{numero:data.numero,venta_id:data.id,duplicada:!!data.duplicada});return 'OK'}
  catch(e){if(!e.response){ventas.value=actualizarVentaOffline(venta.uuid,{error:'Sin conexión con el servidor'});return 'SIN_CONEXION'}
    const mensaje=Object.values(e.response.data?.errors||{})[0]?.[0]||e.response.data?.message||'No se pudo registrar la venta'
    ventas.value=actualizarVentaOffline(venta.uuid,{estado:ERROR,error:mensaje});return 'ERROR'}}

function eliminar(venta){const aviso=venta.exportado?'Ya está registrada en ventas. ¿Quitarla de esta lista?':'Esta venta NO se exportó al sistema. ¿Eliminarla de todos modos?'
  proxy.$alert.dialog(aviso).onOk(()=>{ventas.value=eliminarVentaOffline(venta.uuid);proxy.$alert.info('Venta quitada de la lista')})}
function quitarExportadas(){proxy.$alert.dialog('¿Quitar de la lista las ventas ya exportadas?').onOk(()=>{ventas.value=limpiarExportadas();proxy.$alert.success('Lista depurada')})}
</script>

<style scoped>.summary-card{border-radius:10px}
.items-list{max-width:280px;padding:1px 0}
.venta-link{text-decoration:none;display:inline-flex;align-items:center;gap:2px}.venta-link:hover{text-decoration:underline}
.items-row{font-size:9px;line-height:1.35;color:#5f5f5f;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
</style>
