<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm">
      <div>
        <div class="text-subtitle1 text-weight-bold">{{expired?'Productos vencidos':'Productos por vencer'}}</div>
        <div class="text-caption text-grey-7">{{expired?'Lotes con fecha vencida':'Lotes próximos a su fecha de vencimiento'}} · de compras y de revisiones de almacén</div>
      </div>
      <q-space/>
      <q-input v-if="!expired" v-model.number="days" dense outlined type="number" min="1" max="365" label="Próximos días" style="width:145px" @update:model-value="load"/>
    </div>

    <q-card v-for="group in groups" :key="group.origen" flat bordered class="q-mb-sm">
      <q-card-section class="row items-center q-py-sm">
        <q-avatar :icon="group.icon" :color="group.avatarBg" :text-color="group.color" size="32px"/>
        <div class="q-ml-sm"><div class="text-subtitle2 text-weight-bold">{{group.titulo}}</div><div class="text-caption text-grey-7">{{group.subtitulo}}</div></div>
        <q-space/>
        <q-badge :color="group.color" :label="`${group.rows.length} lote${group.rows.length===1?'':'s'}`"/>
      </q-card-section>
      <q-separator/>
      <q-table flat dense :rows="group.rows" :columns="columns" row-key="id" :loading="loading" :pagination="{rowsPerPage:0}" hide-pagination>
        <template #body-cell-producto="p"><q-td :props="p"><b>{{p.row.producto?.nombre}}</b><div class="text-caption text-grey-7">{{p.row.producto?.codigo}}</div></q-td></template>
        <template #body-cell-origen="p"><q-td :props="p">
          <div class="text-weight-medium">{{p.row.documento||'—'}}</div>
          <div class="text-caption text-grey-7">{{p.row.documento_detalle||'—'}}</div>
        </q-td></template>
        <template #body-cell-cantidad="p"><q-td :props="p" class="text-right">{{qty(p.value,p.row.producto?.unidad)}} {{p.row.producto?.unidad}}</q-td></template>
        <template #body-cell-fecha="p"><q-td :props="p" class="text-center">{{date(p.value)}}</q-td></template>
        <template #body-cell-dias="p"><q-td :props="p" class="text-center"><q-badge :color="p.value<0?'negative':p.value<=7?'orange':'warning'" :label="p.value<0?`${Math.abs(p.value)} días vencido`:`${p.value} días`"/></q-td></template>
        <template #no-data><div class="full-width text-center text-grey-6 q-py-lg"><q-icon name="event_available" size="32px"/><div>{{group.vacio}}</div></div></template>
      </q-table>
    </q-card>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, onMounted, ref, watch } from 'vue'
const props=defineProps({estado:{type:String,default:'por_vencer'}})
const {proxy}=getCurrentInstance(),rows=ref([]),loading=ref(false),days=ref(30)
const expired=computed(()=>props.estado==='vencido')
const columns=[
  {name:'producto',label:'Producto',field:'producto',align:'left'},
  {name:'lote',label:'Lote',field:r=>r.lote||'sin lote',align:'left'},
  {name:'origen',label:'Documento',field:'documento',align:'left'},
  {name:'cantidad',label:'Disponible',field:'cantidad_disponible',align:'right'},
  {name:'fecha',label:'Vencimiento',field:'fecha_vencimiento',align:'center'},
  {name:'dias',label:'Estado',field:'dias_vencimiento',align:'center'}
]
const qty=(v,u)=>Number(v||0).toFixed(u==='KG'?3:0)
const date=v=>new Date(`${String(v).slice(0,10)}T12:00:00`).toLocaleDateString('es-BO')
// Primero los lotes que entraron por compra y abajo los que salieron de una revisión de almacén.
const groups=computed(()=>[
  {origen:'COMPRA',titulo:'Lotes de compras',subtitulo:'Ingresados al registrar una compra',icon:'shopping_bag',color:'primary',avatarBg:'blue-1',
   vacio:'Sin lotes de compras en este rango',rows:rows.value.filter(r=>r.origen!=='ALMACEN')},
  {origen:'ALMACEN',titulo:'Lotes de revisiones de almacén',subtitulo:'Cargados al contar el stock físico de la tienda',icon:'warehouse',color:'deep-orange',avatarBg:'orange-1',
   vacio:'Sin lotes de revisiones en este rango',rows:rows.value.filter(r=>r.origen==='ALMACEN')}
])
function load(){
  loading.value=true
  proxy.$axios.get('/vencimientos',{params:{estado:props.estado,dias:days.value}})
    .then(r=>rows.value=r.data)
    .catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudieron cargar los vencimientos'))
    .finally(()=>loading.value=false)
}
watch(()=>props.estado,load);onMounted(load)
</script>
