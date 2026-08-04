<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm">
      <div>
        <div class="text-subtitle1 text-weight-bold">Avance de la revisión {{almacen.numero}}
          <q-badge :color="stateColor" :label="almacen.estado==='BORRADOR'?'EN REVISIÓN':almacen.estado" class="q-ml-xs"/>
        </div>
        <div class="text-caption text-grey-7">
          <template v-if="almacen.estado==='APLICADO'">Aplicado por {{almacen.aplicado_por_nombre||'—'}} · {{formatDate(almacen.fecha_aplicado)}}</template>
          <template v-else>Compara lo contado con el stock del sistema antes de actualizar los productos</template>
        </div>
      </div>
      <q-space/>
      <q-btn v-if="editable" dense flat icon="fact_check" label="Seguir llenando" no-caps class="q-mr-xs" :to="`/almacenes/${id}`"/>
      <q-btn dense flat round icon="refresh" :loading="loading" class="q-mr-xs" @click="load()"><q-tooltip>Actualizar</q-tooltip></q-btn>
      <q-btn v-if="editable&&can('Aplicar Almacenes')" dense unelevated color="positive" icon="published_with_changes" label="Actualizar productos" no-caps :loading="applying" :disable="!data.revisados" @click="apply"/>
    </div>

    <div class="kpi-row q-mb-sm">
      <q-card flat bordered class="kpi-card"><q-card-section class="q-pa-sm">
        <div class="text-caption text-grey-7">Productos revisados</div>
        <div class="text-h6 text-weight-bold">{{data.revisados}} <span class="text-caption text-grey-6">de {{data.total_productos}}</span></div>
        <q-linear-progress :value="progress" color="primary" track-color="grey-3" size="6px" rounded class="q-mt-xs"/>
      </q-card-section></q-card>
      <q-card flat bordered class="kpi-card"><q-card-section class="q-pa-sm row items-center"><q-avatar icon="check_circle" color="green-1" text-color="positive" size="36px"/><div class="q-ml-sm"><div class="text-caption text-grey-7">Cuadran</div><div class="text-h6 text-weight-bold">{{data.sin_diferencia}}</div></div></q-card-section></q-card>
      <q-card flat bordered class="kpi-card"><q-card-section class="q-pa-sm row items-center"><q-avatar icon="report_problem" color="orange-1" text-color="orange-9" size="36px"/><div class="q-ml-sm"><div class="text-caption text-grey-7">Con diferencia</div><div class="text-h6 text-weight-bold">{{data.con_diferencia}}</div></div></q-card-section></q-card>
      <q-card flat bordered class="kpi-card"><q-card-section class="q-pa-sm row items-center"><q-avatar icon="payments" color="purple-1" text-color="purple-9" size="36px"/><div class="q-ml-sm"><div class="text-caption text-grey-7">Valor de la diferencia</div><div class="text-h6 text-weight-bold" :class="data.diferencia_valor<0?'text-negative':''">Bs {{money(data.diferencia_valor)}}</div></div></q-card-section></q-card>
    </div>

    <q-card v-if="data.por_usuario?.length" flat bordered class="q-mb-sm">
      <q-card-section class="q-pa-sm row items-center">
        <div class="text-caption text-grey-7 q-mr-sm">Quién contó:</div>
        <div class="row q-gutter-xs"><q-badge v-for="u in data.por_usuario" :key="u.usuario" outline color="primary" :label="`${u.usuario}: ${u.productos}`"/></div>
      </q-card-section>
    </q-card>

    <q-card flat bordered>
      <q-card-section class="row items-center q-py-sm">
        <q-icon name="difference" color="primary" size="20px" class="q-mr-xs"/><b>Detalle de la revisión</b>
        <q-space/>
        <q-checkbox v-model="onlyDifferences" dense size="sm" label="Sólo diferencias" class="text-caption"/>
      </q-card-section>
      <q-separator/>
      <q-table flat dense :rows="rows" :columns="columns" row-key="id" :loading="loading" :pagination="{rowsPerPage:0}" hide-pagination>
        <template #body-cell-producto="p"><q-td :props="p"><b>{{p.row.nombre}}</b><div class="text-caption text-grey-7">{{p.row.codigo}} · {{p.row.unidad}}</div></q-td></template>
        <template #body-cell-sistema="p"><q-td :props="p" class="text-right">{{qty(p.row.stock_actual,p.row.unidad)}}</q-td></template>
        <template #body-cell-contado="p"><q-td :props="p" class="text-right text-weight-bold">{{qty(p.row.cantidad,p.row.unidad)}}</q-td></template>
        <template #body-cell-diferencia="p"><q-td :props="p" class="text-right"><q-badge :color="diffColor(p.row)" :label="diffLabel(p.row)"/></q-td></template>
        <template #body-cell-lote="p"><q-td :props="p">
          <div v-if="p.row.conteos?.length" class="lot-cell">
            <q-badge v-for="lot in p.row.conteos" :key="lot.id" outline color="deep-orange"
                     :label="`${lot.lote||'sin lote'} · ${qty(lot.cantidad,p.row.unidad)}${lot.fecha_vencimiento?' · vence '+shortDate(lot.fecha_vencimiento):''}`"/>
          </div>
          <span v-else class="text-grey-6">—</span>
        </q-td></template>
        <template #body-cell-resultado="p"><q-td :props="p" class="text-right">
          <span v-if="p.row.stock_nuevo!==null&&p.row.stock_nuevo!==undefined" class="text-caption"><span class="text-grey-6">{{qty(p.row.stock_anterior,p.row.unidad)}}</span> → <b>{{qty(p.row.stock_nuevo,p.row.unidad)}}</b></span>
          <span v-else class="text-caption text-grey-6">quedará en {{qty(p.row.cantidad,p.row.unidad)}}</span>
        </q-td></template>
        <template #no-data><div class="full-width text-center text-grey-6 q-py-xl"><q-icon name="inventory" size="42px"/><div>{{onlyDifferences?'No hay diferencias':'Todavía no se contó ningún producto'}}</div></div></template>
      </q-table>
    </q-card>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
const {proxy}=getCurrentInstance(),route=useRoute(),router=useRouter()
const id=Number(route.params.id)
const almacen=reactive({numero:'',estado:'BORRADOR',descripcion:'',aplicado_por_nombre:null,fecha_aplicado:null})
const data=reactive({detalles:[],total_productos:0,revisados:0,con_diferencia:0,sin_diferencia:0,diferencia_valor:0,por_usuario:[]})
const loading=ref(false),applying=ref(false),onlyDifferences=ref(false)
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
// Botón "Actualizar productos": el stock de cada producto contado pasa a ser lo revisado.
function apply(){
  const aviso=data.con_diferencia
    ? `${data.con_diferencia} de ${data.revisados} productos tienen diferencia.`
    : `Los ${data.revisados} productos revisados cuadran con el sistema.`
  proxy.$alert.dialog(`${aviso} ¿Actualizar los productos con esta revisión? El stock pasará a ser el contado y se guardará el valor anterior de cada uno.`).onOk(async()=>{
    applying.value=true
    try{
      await proxy.$axios.post(`/almacenes/${id}/aplicar`)
      proxy.$alert.success('Productos actualizados con la revisión')
      await load()
    }catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo actualizar los productos')}
    finally{applying.value=false}
  })
}
onMounted(()=>{
  load()
  refreshTimer=setInterval(()=>{if(!document.hidden&&editable.value&&!applying.value)load()},15000)
})
onBeforeUnmount(()=>clearInterval(refreshTimer))
</script>

<style scoped>
.kpi-row{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}.kpi-card{border-radius:10px}
.lot-cell{display:flex;flex-direction:column;align-items:flex-start;gap:2px}
@media(max-width:900px){.kpi-row{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
