<template>
  <q-page class="q-pa-xs">
    <div class="row items-center q-mb-xs">
      <div>
        <div class="text-body2 text-weight-bold">Avance de la revisión {{almacen.numero}}
          <q-badge :color="stateColor" :label="almacen.estado==='BORRADOR'?'EN REVISIÓN':almacen.estado" class="q-ml-xs"/>
        </div>
        <div class="hint">
          <template v-if="almacen.estado==='APLICADO'">Aplicado por {{almacen.aplicado_por_nombre||'—'}} · {{formatDate(almacen.fecha_aplicado)}}</template>
          <template v-else>Compara lo contado con el stock del sistema antes de actualizar los productos</template>
        </div>
      </div>
      <q-space/>
      <q-btn v-if="editable" dense flat size="sm" icon="fact_check" label="Seguir llenando" no-caps class="q-mr-xs" :to="`/almacenes/${id}`"/>
      <q-btn-dropdown dense flat size="sm" color="green-8" icon="summarize" label="Reportes" no-caps class="q-mr-xs" :loading="exporting">
        <q-list dense style="min-width:290px">
          <q-item-label header class="q-py-xs text-caption">Avance de la revisión</q-item-label>
          <q-item clickable v-close-popup @click="download('avance','excel')"><q-item-section avatar><q-icon name="table_view" color="green-8"/></q-item-section><q-item-section><q-item-label>Excel</q-item-label><q-item-label caption>Contado vs sistema y quién contó</q-item-label></q-item-section></q-item>
          <q-item clickable v-close-popup @click="download('avance','pdf')"><q-item-section avatar><q-icon name="picture_as_pdf" color="red-8"/></q-item-section><q-item-section><q-item-label>PDF</q-item-label><q-item-label caption>Para imprimir y firmar</q-item-label></q-item-section></q-item>
          <q-separator/>
          <q-item-label header class="q-py-xs text-caption">Capital en almacén</q-item-label>
          <q-item clickable v-close-popup @click="download('capital','excel')"><q-item-section avatar><q-icon name="table_view" color="green-8"/></q-item-section><q-item-section><q-item-label>Excel</q-item-label><q-item-label caption>Precios, stock antes y después, valor a costo y venta</q-item-label></q-item-section></q-item>
          <q-item clickable v-close-popup @click="download('capital','pdf')"><q-item-section avatar><q-icon name="picture_as_pdf" color="red-8"/></q-item-section><q-item-section><q-item-label>PDF</q-item-label><q-item-label caption>Cuánto hay hoy y cuánto quedará</q-item-label></q-item-section></q-item>
        </q-list>
      </q-btn-dropdown>
      <q-btn dense flat round size="sm" icon="refresh" :loading="loading" class="q-mr-xs" @click="load()"><q-tooltip>Actualizar</q-tooltip></q-btn>
      <q-btn v-if="editable&&can('Aplicar Almacenes')" dense unelevated size="sm" color="positive" icon="published_with_changes" label="Actualizar productos" no-caps :loading="applying" :disable="!data.revisados" @click="apply"/>
    </div>

    <div class="kpi-row q-mb-xs">
      <div class="kpi">
        <div class="col">
          <div class="kpi__label">Productos revisados</div>
          <div class="kpi__value">{{data.revisados}}<span class="kpi__hint">de {{data.total_productos}}</span></div>
          <q-linear-progress :value="progress" color="primary" track-color="grey-3" size="4px" rounded class="q-mt-xs"/>
        </div>
      </div>
      <div class="kpi kpi--ok"><q-icon name="check_circle" size="16px" class="kpi__icon"/><div><div class="kpi__label">Cuadran</div><div class="kpi__value">{{data.sin_diferencia}}</div></div></div>
      <div class="kpi kpi--warn"><q-icon name="report_problem" size="16px" class="kpi__icon"/><div><div class="kpi__label">Con diferencia</div><div class="kpi__value">{{data.con_diferencia}}</div></div></div>
      <div class="kpi kpi--money"><q-icon name="payments" size="16px" class="kpi__icon"/><div><div class="kpi__label">Valor de la diferencia</div><div class="kpi__value" :class="data.diferencia_valor<0?'text-negative':''">Bs {{money(data.diferencia_valor)}}</div></div></div>
    </div>

    <div v-if="data.por_usuario?.length" class="who q-mb-xs">
      <span class="hint">Quién contó:</span>
      <q-badge v-for="u in data.por_usuario" :key="u.usuario" outline color="primary" class="mini-badge" :label="`${u.usuario}: ${u.productos}`"/>
    </div>

    <q-card flat bordered>
      <q-card-section class="row items-center q-py-none q-px-sm table-head">
        <q-icon name="difference" color="primary" size="16px" class="q-mr-xs"/><b class="text-caption">Detalle de la revisión</b>
        <q-space/>
        <q-toggle v-model="showPhotos" dense size="xs" color="primary" label="Fotos" class="hint q-mr-md"/>
        <q-checkbox v-model="onlyDifferences" dense size="xs" label="Sólo diferencias" class="hint"/>
      </q-card-section>
      <q-separator/>
      <q-table flat dense class="tight" :rows="rows" :columns="columns" row-key="id" :loading="loading" :pagination="{rowsPerPage:0}" hide-pagination>
        <template #body-cell-producto="p"><q-td :props="p">
          <div class="row items-center no-wrap">
            <q-avatar v-if="showPhotos" square size="30px" class="thumb q-mr-xs" @click="zoom(p.row)">
              <img v-if="p.row.foto" :src="photoUrl(p.row.foto)" loading="lazy"/><q-icon v-else name="inventory_2" size="15px" color="grey-5"/>
            </q-avatar>
            <div class="col ellipsis"><div class="text-weight-medium ellipsis">{{p.row.nombre}}</div><div class="cell-sub">{{p.row.codigo}} · {{p.row.unidad}}</div></div>
          </div>
        </q-td></template>
        <template #body-cell-sistema="p"><q-td :props="p" class="text-right">{{qty(p.row.stock_actual,p.row.unidad)}}</q-td></template>
        <template #body-cell-contado="p"><q-td :props="p" class="text-right text-weight-bold">{{qty(p.row.cantidad,p.row.unidad)}}</q-td></template>
        <template #body-cell-diferencia="p"><q-td :props="p" class="text-right"><q-badge :color="diffColor(p.row)" class="mini-badge" :label="diffLabel(p.row)"/></q-td></template>
        <template #body-cell-lote="p"><q-td :props="p">
          <div v-if="p.row.conteos?.length" class="lot-cell">
            <q-badge v-for="lot in p.row.conteos" :key="lot.id" outline color="deep-orange" class="mini-badge"
                     :label="`${lot.lote||'sin lote'} · ${qty(lot.cantidad,p.row.unidad)}${lot.fecha_vencimiento?' · vence '+shortDate(lot.fecha_vencimiento):''}`"/>
          </div>
          <span v-else class="text-grey-6">—</span>
        </q-td></template>
        <template #body-cell-resultado="p"><q-td :props="p" class="text-right">
          <span v-if="p.row.stock_nuevo!==null&&p.row.stock_nuevo!==undefined"><span class="text-grey-6">{{qty(p.row.stock_anterior,p.row.unidad)}}</span> → <b>{{qty(p.row.stock_nuevo,p.row.unidad)}}</b></span>
          <span v-else class="text-grey-6">quedará en {{qty(p.row.cantidad,p.row.unidad)}}</span>
        </q-td></template>
        <template #no-data><div class="full-width text-center text-grey-6 q-py-md"><q-icon name="inventory" size="28px"/><div class="hint">{{onlyDifferences?'No hay diferencias':'Todavía no se contó ningún producto'}}</div></div></template>
      </q-table>
    </q-card>

    <q-dialog v-model="confirmDialog" :maximized="$q.screen.lt.sm">
      <q-card class="column no-wrap confirm-card">
        <q-card-section class="row items-center q-pa-sm confirm-head">
          <q-avatar color="white" text-color="positive" icon="published_with_changes" size="30px"/>
          <div class="q-ml-sm">
            <div class="text-body2 text-weight-bold">Actualizar productos con la revisión {{almacen.numero}}</div>
            <div class="text-caption">Revisa las diferencias antes de que el stock pase a ser lo contado</div>
          </div>
          <q-space/><q-btn flat round dense icon="close" color="white" v-close-popup/>
        </q-card-section>

        <q-card-section class="q-pa-xs">
          <div class="confirm-kpis">
            <div class="confirm-kpi"><div class="confirm-kpi__label">Productos revisados</div><div class="confirm-kpi__value">{{data.revisados}}</div></div>
            <div class="confirm-kpi confirm-kpi--up"><div class="confirm-kpi__label">Sobrantes</div><div class="confirm-kpi__value">+{{fmt(surplus.total)}}<span class="confirm-kpi__hint">{{surplus.count}} producto{{surplus.count===1?'':'s'}}</span></div></div>
            <div class="confirm-kpi confirm-kpi--down"><div class="confirm-kpi__label">Faltantes</div><div class="confirm-kpi__value">−{{fmt(shortage.total)}}<span class="confirm-kpi__hint">{{shortage.count}} producto{{shortage.count===1?'':'s'}}</span></div></div>
            <div class="confirm-kpi"><div class="confirm-kpi__label">Cuadran</div><div class="confirm-kpi__value">{{data.sin_diferencia}}</div></div>
            <div class="confirm-kpi" :class="data.diferencia_valor<0?'confirm-kpi--down':'confirm-kpi--up'"><div class="confirm-kpi__label">Valor neto</div><div class="confirm-kpi__value">Bs {{money(data.diferencia_valor)}}</div></div>
          </div>
        </q-card-section>

        <q-separator/>
        <q-card-section class="row items-center q-py-xs q-px-sm">
          <q-icon name="difference" size="16px" color="deep-orange" class="q-mr-xs"/>
          <b class="text-caption">{{confirmAll?'Todos los productos revisados':'Productos con diferencia'}} ({{confirmRows.length}})</b>
          <q-space/>
          <q-toggle v-model="confirmAll" dense size="xs" color="primary" label="Ver también los que cuadran" class="hint"/>
        </q-card-section>
        <q-separator/>

        <div class="col scroll confirm-table">
          <q-markup-table dense flat separator="horizontal" class="tight">
            <thead><tr>
              <th class="text-left" colspan="2">Producto</th><th class="text-right">Sistema</th><th class="text-right">Contado</th>
              <th class="text-right">Diferencia</th><th class="text-right">Quedará en</th><th class="text-right">Valor Bs</th>
            </tr></thead>
            <tbody>
              <tr v-for="row in confirmRows" :key="row.id" :class="rowClass(row)">
                <td class="thumb-cell"><q-avatar square size="28px" class="thumb" @click="zoom(row)"><img v-if="row.foto" :src="photoUrl(row.foto)" loading="lazy"/><q-icon v-else name="inventory_2" size="14px" color="grey-5"/></q-avatar></td>
                <td>
                  <div class="text-weight-medium">{{row.nombre}}</div>
                  <div class="cell-sub">{{row.codigo}} · contó {{row.usuario_nombre||'—'}}</div>
                  <div v-if="row.conteos?.length" class="cell-sub text-deep-orange-9">{{lotSummary(row)}}</div>
                </td>
                <td class="text-right">{{qty(row.stock_actual,row.unidad)}}</td>
                <td class="text-right text-weight-bold">{{qty(row.cantidad,row.unidad)}}</td>
                <td class="text-right"><q-badge :color="diffColor(row)" class="mini-badge" :label="diffLabel(row)"/></td>
                <td class="text-right"><span class="text-grey-6">{{qty(row.stock_actual,row.unidad)}}</span> → <b>{{qty(row.cantidad,row.unidad)}}</b></td>
                <td class="text-right" :class="rowValue(row)<0?'text-negative':rowValue(row)>0?'text-positive':'text-grey-6'">{{money(rowValue(row))}}</td>
              </tr>
              <tr v-if="!confirmRows.length"><td colspan="7" class="text-center text-grey-6 q-py-md">Todo cuadra con el sistema</td></tr>
            </tbody>
          </q-markup-table>
        </div>

        <q-separator/>
        <q-card-section class="q-py-xs bg-orange-1 text-orange-10 hint row items-center">
          <q-icon name="info" size="14px" class="q-mr-xs"/>
          Los {{Math.max(0,data.total_productos-data.revisados)}} productos que nadie contó no se modifican. Se guardará el valor anterior de cada producto para poder anular la revisión.
        </q-card-section>
        <q-separator/>
        <q-card-actions align="right" class="q-pa-xs">
          <q-btn flat dense label="Cancelar" no-caps v-close-popup/>
          <q-btn dense unelevated color="positive" icon="published_with_changes" label="Sí, actualizar productos" no-caps :loading="applying" @click="runApply"/>
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="photoDialog">
      <q-card class="photo-card">
        <q-img :src="photo.src" fit="contain" style="max-height:70vh"/>
        <q-card-section class="q-pa-sm text-center"><div class="text-weight-medium">{{photo.nombre}}</div><div class="cell-sub">{{photo.codigo}}</div></q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
const {proxy}=getCurrentInstance(),route=useRoute(),router=useRouter()
const id=Number(route.params.id)
const almacen=reactive({numero:'',estado:'BORRADOR',descripcion:'',aplicado_por_nombre:null,fecha_aplicado:null})
const data=reactive({detalles:[],total_productos:0,revisados:0,con_diferencia:0,sin_diferencia:0,diferencia_valor:0,por_usuario:[]})
const loading=ref(false),applying=ref(false),exporting=ref(false),onlyDifferences=ref(false),confirmDialog=ref(false),confirmAll=ref(false)
// Miniaturas del producto: se pueden apagar para ganar densidad y la preferencia queda guardada.
const showPhotos=ref(localStorage.getItem('avanceFotos')!=='0'),photoDialog=ref(false),photo=reactive({src:'',nombre:'',codigo:''})
watch(showPhotos,v=>localStorage.setItem('avanceFotos',v?'1':'0'))
const photoUrl=path=>`${proxy.$imgBase}/images/${path}`
function zoom(row){if(!row.foto)return;Object.assign(photo,{src:photoUrl(row.foto),nombre:row.nombre,codigo:row.codigo});photoDialog.value=true}
let refreshTimer=null
const can=p=>proxy.$store.hasPermission(p),money=v=>Number(v||0).toFixed(2)
const qty=(value,unit)=>Number(value||0).toFixed(unit==='KG'?3:0)
const formatDate=value=>value?new Date(value).toLocaleString('es-BO'):''
const shortDate=value=>value?new Date(`${String(value).slice(0,10)}T12:00:00`).toLocaleDateString('es-BO'):''
const editable=computed(()=>almacen.estado==='BORRADOR')
const stateColor=computed(()=>almacen.estado==='APLICADO'?'positive':almacen.estado==='ANULADO'?'grey-6':'orange')
const progress=computed(()=>data.total_productos?Math.min(1,data.revisados/data.total_productos):0)
const diff=row=>Number(row.diferencia_actual||0)
const diffLabel=row=>`${diff(row)>0?'+':''}${diff(row).toFixed(row.unidad==='KG'?3:0)}`
const diffColor=row=>Math.abs(diff(row))<0.0005?'grey-6':diff(row)>0?'positive':'negative'
const rows=computed(()=>onlyDifferences.value?data.detalles.filter(d=>Math.abs(diff(d))>0.0005):data.detalles)
// Resumen del diálogo de confirmación: sobrantes, faltantes y valor de cada línea.
const differences=computed(()=>data.detalles.filter(d=>Math.abs(diff(d))>0.0005))
const confirmRows=computed(()=>confirmAll.value?data.detalles:differences.value)
const surplus=computed(()=>{const list=differences.value.filter(d=>diff(d)>0);return {count:list.length,total:list.reduce((s,d)=>s+diff(d),0)}})
const shortage=computed(()=>{const list=differences.value.filter(d=>diff(d)<0);return {count:list.length,total:Math.abs(list.reduce((s,d)=>s+diff(d),0))}})
const fmt=value=>Number(value||0).toFixed(3).replace(/\.?0+$/,'')||'0'
const rowValue=row=>Number((diff(row)*Number(row.precio_compra||0)).toFixed(2))
const rowClass=row=>Math.abs(diff(row))<0.0005?'':diff(row)>0?'row-up':'row-down'
const lotSummary=row=>(row.conteos||[]).map(l=>`${l.lote||'sin lote'}: ${qty(l.cantidad,row.unidad)}${l.fecha_vencimiento?` (vence ${shortDate(l.fecha_vencimiento)})`:''}`).join(' · ')
const columns=[
  {name:'producto',label:'Producto',field:'nombre',align:'left'},
  {name:'usuario',label:'Contó',field:r=>r.usuario_nombre||'—',align:'left'},
  {name:'sistema',label:'Sistema',field:'stock_actual',align:'right'},
  {name:'contado',label:'Contado',field:'cantidad',align:'right'},
  {name:'diferencia',label:'Diferencia',field:'diferencia_actual',align:'right'},
  {name:'lote',label:'Lote / vencimiento',field:'lote',align:'left'},
  {name:'resultado',label:'Stock resultante',field:'stock_nuevo',align:'right'}
]

async function load(){
  loading.value=true
  try{
    const response=(await proxy.$axios.get(`/almacenes/${id}/avance`)).data
    Object.assign(almacen,response.almacen)
    Object.assign(data,{...response,detalles:response.detalles||[]})
  }catch(e){
    proxy.$alert.error(e.response?.data?.message||'No se pudo cargar el avance')
    if(e.response?.status===404)router.replace('/almacenes')
  }finally{loading.value=false}
}
// Reportes: avance (contado vs sistema) y capital (precios y valor antes/después), en Excel o PDF.
async function download(reporte,formato){
  exporting.value=true
  try{
    const response=await proxy.$axios.get(`/almacenes/${id}/${reporte}-exportar/${formato}`,{responseType:'blob'})
    const url=URL.createObjectURL(response.data),a=document.createElement('a')
    a.href=url;a.download=`${reporte}_${almacen.numero||id}.${formato==='excel'?'xlsx':'pdf'}`;a.click();URL.revokeObjectURL(url)
  }catch{proxy.$alert.error(`No se pudo exportar el reporte de ${reporte}`)}
  finally{exporting.value=false}
}
// Botón "Actualizar productos": primero se revisan las diferencias en el diálogo.
function apply(){confirmAll.value=false;confirmDialog.value=true}
async function runApply(){
  applying.value=true
  try{
    await proxy.$axios.post(`/almacenes/${id}/aplicar`)
    confirmDialog.value=false
    proxy.$alert.success('Productos actualizados con la revisión')
    await load()
  }catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo actualizar los productos')}
  finally{applying.value=false}
}
onMounted(()=>{
  load()
  refreshTimer=setInterval(()=>{if(!document.hidden&&editable.value&&!applying.value)load()},15000)
})
onBeforeUnmount(()=>clearInterval(refreshTimer))
</script>

<style scoped>
.hint{font-size:11px;color:#78909c}
.cell-sub{font-size:10px;line-height:12px;color:#78909c}
.mini-badge{font-size:10px;padding:1px 5px;line-height:13px}
.kpi-row{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px}
.kpi{display:flex;align-items:center;gap:6px;border:1px solid #e0e6ea;border-radius:8px;padding:4px 8px;background:#fff}
.kpi>div{min-width:0}
.kpi__icon{flex:0 0 auto}
.kpi__label{font-size:10px;line-height:12px;color:#607d8b}
.kpi__value{font-size:15px;font-weight:700;line-height:18px;color:#263238}
.kpi__hint{font-size:10px;font-weight:500;color:#90a4ae;margin-left:4px}
.kpi--ok{border-color:#c8e6c9}.kpi--ok .kpi__icon{color:#21ba45}
.kpi--warn{border-color:#ffe0b2}.kpi--warn .kpi__icon{color:#f57c00}
.kpi--money{border-color:#e1bee7}.kpi--money .kpi__icon{color:#8e24aa}
.who{display:flex;flex-wrap:wrap;align-items:center;gap:4px}
.table-head{min-height:30px}
.thumb{border-radius:4px;background:#f1f4f6;border:1px solid #e0e6ea;cursor:pointer;flex:0 0 auto}
.thumb img{object-fit:cover}
.thumb-cell{width:34px;padding-right:0!important}
.tight :deep(thead th){padding:3px 6px;height:auto;font-size:10px;text-transform:uppercase;letter-spacing:.3px;color:#78909c;white-space:nowrap}
.tight :deep(tbody td){padding:2px 6px;height:auto;font-size:11.5px}
.tight :deep(tbody tr:nth-child(even)){background:#fafcfd}
.lot-cell{display:flex;flex-direction:column;align-items:flex-start;gap:1px}
.photo-card{max-width:92vw}
.confirm-card{width:900px;max-width:96vw;max-height:92vh}
.confirm-head{background:linear-gradient(135deg,#21ba45,#0f8a30);color:#fff}
.confirm-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:6px}
.confirm-kpi{border:1px solid #e0e6ea;border-radius:8px;padding:4px 8px;background:#fafcfd}
.confirm-kpi--up{border-color:#c8e6c9;background:#f4fbf5}.confirm-kpi--down{border-color:#ffcdd2;background:#fff6f6}
.confirm-kpi__label{font-size:10px;color:#607d8b;line-height:12px}
.confirm-kpi__value{font-size:15px;font-weight:800;line-height:19px;color:#263238}
.confirm-kpi--up .confirm-kpi__value{color:#1b5e20}.confirm-kpi--down .confirm-kpi__value{color:#c62828}
.confirm-kpi__hint{display:block;font-size:10px;font-weight:500;color:#78909c;line-height:12px}
.confirm-table{min-height:160px}
.confirm-table tr.row-up td{background:#f4fbf5}.confirm-table tr.row-down td{background:#fff6f6}
@media(max-width:700px){.confirm-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:900px){.kpi-row{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
