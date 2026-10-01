<template>
  <q-page class="ganancias q-pa-sm">
    <div class="hero q-mb-sm">
      <div class="col-grow"><div class="text-h6 text-weight-bold row items-center no-wrap"><q-icon name="trending_up" class="q-mr-xs"/>Control de ganancias</div><div class="text-caption hero-subtitle">{{rangeLabel}} · ventas menos costo de compra y descuentos</div></div>
      <q-btn-toggle v-model="preset" :options="presetOptions" no-caps dense unelevated toggle-color="white" toggle-text-color="primary" color="transparent" text-color="white" class="period-toggle" @update:model-value="applyPreset"/>
      <div class="row items-center no-wrap q-gutter-xs range-dates"><q-input v-model="from" type="date" dense outlined dark label="Desde" @update:model-value="onDates"/><q-input v-model="to" type="date" dense outlined dark label="Hasta" @update:model-value="onDates"/></div>
    </div>

    <q-card flat bordered class="panel q-mb-sm">
      <q-card-section class="row q-col-gutter-sm items-center q-pa-sm">
        <q-select v-model="categories" :options="catalog.categorias" option-label="nombre" option-value="id" emit-value map-options multiple use-chips dense outlined clearable label="Categorías" class="col-12 col-md-4" @update:model-value="onCategories">
          <template #prepend><q-icon name="category" size="18px"/></template>
          <template #selected-item="s"><q-chip dense removable size="sm" :style="chipStyle(s.opt.color)" class="q-ma-none q-mr-xs" @remove="s.removeAtIndex(s.index)">{{s.opt.nombre}}</q-chip></template>
          <template #option="s"><q-item v-bind="s.itemProps"><q-item-section avatar><span class="dot" :style="{background:s.opt.color||'#9e9e9e'}"/></q-item-section><q-item-section>{{s.opt.nombre}}</q-item-section><q-item-section side><q-icon v-if="s.selected" name="check" color="primary"/></q-item-section></q-item></template>
        </q-select>
        <q-select v-model="products" :options="productOptions" option-label="nombre" option-value="id" emit-value map-options multiple use-chips use-input input-debounce="0" dense outlined clearable label="Productos" class="col-12 col-md-4" @filter="filterProducts" @update:model-value="load">
          <template #prepend><q-icon name="inventory_2" size="18px"/></template>
          <template #option="s"><q-item v-bind="s.itemProps" dense><q-item-section><q-item-label>{{s.opt.nombre}}</q-item-label><q-item-label caption>{{s.opt.codigo}}</q-item-label></q-item-section><q-item-section side><q-icon v-if="s.selected" name="check" color="primary"/></q-item-section></q-item></template>
          <template #no-option><q-item><q-item-section class="text-grey-6">Sin coincidencias</q-item-section></q-item></template>
        </q-select>
        <q-select v-model="user" :options="catalog.usuarios" option-label="name" option-value="id" emit-value map-options dense outlined clearable label="Vendedor" class="col-8 col-md-2" @update:model-value="load"><template #prepend><q-icon name="badge" size="18px"/></template></q-select>
        <div class="col-4 col-md-2 row justify-end no-wrap"><q-btn dense flat no-caps color="grey-8" icon="filter_alt_off" label="Limpiar" :disable="!hasFilters" @click="clearFilters"/><q-btn dense flat round color="primary" icon="refresh" :loading="loading" @click="load"/></div>
      </q-card-section>
    </q-card>

    <div class="kpi-grid q-mb-sm">
      <q-card v-for="k in kpis" :key="k.label" flat bordered class="kpi-card" :class="{'kpi-main':k.main}">
        <q-card-section class="row items-center no-wrap q-pa-sm">
          <q-avatar :class="`kpi-icon kpi-${k.color}`" :icon="k.icon" size="38px"/>
          <div class="q-ml-sm col" style="min-width:0">
            <div class="kpi-label">{{k.label}}</div>
            <div class="kpi-value" :class="{'text-negative':k.value<0}">{{k.prefix}}{{k.format(k.value)}}{{k.suffix}}</div>
            <div class="kpi-caption row items-center no-wrap"><template v-if="k.delta!==undefined"><span v-if="k.delta===null" class="text-grey-6">sin datos del periodo anterior</span><span v-else :class="k.delta>=0?'text-positive':'text-negative'" class="row items-center no-wrap"><q-icon :name="k.delta>=0?'arrow_upward':'arrow_downward'" size="12px"/>{{Math.abs(k.delta).toFixed(1)}}% <span class="text-grey-6 q-ml-xs">vs periodo anterior</span></span></template><span v-else class="text-grey-6 ellipsis">{{k.caption}}</span></div>
          </div>
        </q-card-section>
        <q-tooltip v-if="k.delta!==undefined&&k.delta!==null" class="bg-grey-9">Periodo anterior: {{k.prefix}}{{k.format(k.previous)}}{{k.suffix}}</q-tooltip>
      </q-card>
    </div>

    <div class="row q-col-gutter-sm q-mb-sm">
      <div class="col-12 col-lg-8"><q-card flat bordered class="panel full-height">
        <q-card-section class="row items-center q-py-xs q-px-sm"><div><div class="card-title">Evolución de la ganancia</div><div class="card-sub">Por {{granularityLabel}} · toca un punto para ver las ventas de ese momento</div></div><q-space/><div v-if="best" class="best-chip"><q-icon name="emoji_events" size="14px" color="amber-8"/> Mejor {{granularityLabel}}: <b>{{best.label}}</b> · Bs {{money(best.ganancia)}}</div></q-card-section>
        <q-card-section class="q-pa-none"><apexchart type="line" height="280" :options="trendOptions" :series="trendSeries"/></q-card-section>
      </q-card></div>
      <div class="col-12 col-lg-4"><q-card flat bordered class="panel full-height">
        <q-card-section class="q-py-xs q-px-sm"><div class="card-title">Ganancia por categoría</div><div class="card-sub">Toca una barra para filtrar por esa categoría</div></q-card-section>
        <q-card-section class="q-pa-none"><apexchart v-if="data.categorias.length" type="bar" :height="Math.max(280,data.categorias.length*30)" :options="categoryOptions" :series="categorySeries"/><div v-else class="empty">Sin ventas en este periodo</div></q-card-section>
      </q-card></div>
    </div>

    <q-card flat bordered class="panel">
      <q-card-section class="row items-center q-py-xs q-px-sm q-col-gutter-sm">
        <div class="col-12 col-md"><div class="card-title">Ranking de productos por ganancia</div><div class="card-sub">{{tableRows.length}} productos vendidos · toca una fila para ver todas sus ventas</div></div>
        <q-input v-model="tableSearch" dense outlined clearable debounce="200" placeholder="Buscar producto" class="col-8 col-md-3"><template #prepend><q-icon name="search" size="18px"/></template></q-input>
        <div class="col-4 col-md-auto text-right"><q-btn dense flat no-caps color="positive" icon="table_view" label="Exportar CSV" :disable="!tableRows.length" @click="exportCsv"/></div>
      </q-card-section>
      <q-table dense flat :rows="tableRows" :columns="columns" row-key="producto_id" :loading="loading" v-model:pagination="tablePagination" :rows-per-page-options="[25,50,100,0]" class="rank-table" @row-click="(e,row)=>openProduct(row)">
        <template #body-cell-rank="p"><q-td :props="p"><div class="rank" :class="`rank-${p.row.rank<=3?p.row.rank:'n'}`">{{p.row.rank}}</div></q-td></template>
        <template #body-cell-nombre="p"><q-td :props="p"><div class="row items-center no-wrap"><q-avatar rounded size="34px" color="grey-2" class="q-mr-sm"><img v-if="p.row.foto" :src="photoUrl(p.row.foto)" @error="$event.target.style.display='none'"/><q-icon v-else name="inventory_2" size="16px" color="grey-5"/></q-avatar><div style="min-width:0"><div class="text-weight-bold ellipsis product-name">{{p.row.nombre}}</div><div class="text-caption text-grey-7">{{p.row.codigo}} · {{p.row.categoria||'Sin categoría'}}</div></div></div></q-td></template>
        <template #body-cell-ganancia="p"><q-td :props="p"><b :class="p.value<0?'text-negative':'text-positive'">Bs {{money(p.value)}}</b></q-td></template>
        <template #body-cell-margen="p"><q-td :props="p"><div class="margin-cell"><span :class="marginClass(p.value)">{{p.value.toFixed(1)}}%</span><q-linear-progress rounded size="5px" :value="Math.max(0,Math.min(p.value,100))/100" :color="marginColor(p.value)" track-color="grey-3"/></div></q-td></template>
        <template #body-cell-participacion="p"><q-td :props="p"><div class="share-cell"><div class="share-bar" :style="{width:Math.max(0,p.value)+'%'}"/><span>{{p.value.toFixed(1)}}%</span></div></q-td></template>
        <template #body-cell-ver="p"><q-td :props="p"><q-btn dense flat round size="sm" icon="chevron_right" color="primary"/></q-td></template>
        <template #bottom-row><q-tr v-if="tableRows.length" class="total-row"><q-td colspan="3" class="text-right text-weight-bold">TOTAL</q-td><q-td class="text-right">{{units(sum('cantidad'))}}</q-td><q-td class="text-right">Bs {{money(sum('ventas'))}}</q-td><q-td class="text-right">Bs {{money(sum('costo'))}}</q-td><q-td class="text-right"><b :class="sum('ganancia')<0?'text-negative':'text-positive'">Bs {{money(sum('ganancia'))}}</b></q-td><q-td class="text-right">{{(sum('ventas')?sum('ganancia')/sum('ventas')*100:0).toFixed(1)}}%</q-td><q-td colspan="3"/></q-tr></template>
        <template #no-data><div class="full-width empty">Sin ventas con estos filtros</div></template>
      </q-table>
    </q-card>

    <q-dialog v-model="dialog" maximized-on-mobile><q-card class="detail-card">
      <q-card-section class="detail-head row items-center no-wrap q-py-sm">
        <q-avatar rounded size="44px" color="white" class="q-mr-sm"><img v-if="detail.foto" :src="photoUrl(detail.foto)"/><q-icon v-else :name="detail.producto_id?'inventory_2':'schedule'" color="primary"/></q-avatar>
        <div class="col" style="min-width:0"><div class="text-subtitle1 text-weight-bold ellipsis">{{detail.titulo}}</div><div class="text-caption">{{detail.subtitulo}}</div></div>
        <q-btn flat round dense icon="close" v-close-popup/>
      </q-card-section>
      <q-card-section class="q-pa-sm">
        <div class="mini-kpis q-mb-sm"><div v-for="k in detailKpis" :key="k.label" class="mini-kpi"><div class="kpi-label">{{k.label}}</div><div class="mini-value" :class="k.cls">{{k.text}}</div></div></div>
        <q-card v-if="detail.serie.length>1" flat bordered class="q-mb-sm"><apexchart type="area" height="170" :options="detailOptions" :series="detailSeries"/></q-card>
        <q-table dense flat bordered :rows="detail.rows" :columns="detailColumns" row-key="id" :loading="detail.loading" v-model:pagination="detail.pagination" :rows-per-page-options="[25,50,100,200]" @request="loadDetail" class="detail-table">
          <template #body-cell-numero="p"><q-td :props="p"><div class="text-weight-bold text-primary">{{p.value}}</div><div class="text-caption text-grey-7">{{p.row.usuario_nombre}} · Caja {{p.row.caja??1}}</div></q-td></template>
          <template #body-cell-tipo_pago="p"><q-td :props="p"><q-badge outline :color="p.value==='EFECTIVO'?'green':p.value==='QR'?'blue':'purple'" :label="p.value"/></q-td></template>
          <template #body-cell-ganancia="p"><q-td :props="p"><b :class="p.value<0?'text-negative':'text-positive'">Bs {{money(p.value)}}</b></q-td></template>
          <template #body-cell-margen="p"><q-td :props="p"><span :class="marginClass(p.value)">{{p.value.toFixed(1)}}%</span></q-td></template>
          <template #no-data><div class="full-width empty">Sin ventas</div></template>
        </q-table>
      </q-card-section>
    </q-card></q-dialog>
  </q-page>
</template>

<script setup>
import {computed,getCurrentInstance,onMounted,reactive,ref} from 'vue'
import VueApexCharts from 'vue3-apexcharts'
const apexchart=VueApexCharts
const {proxy}=getCurrentInstance()
// Pantalla de control: por defecto el mes en curso; todo (indicadores, gráfico, ranking) responde a los mismos filtros.
const ymd=d=>`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
const presets={hoy:()=>{const t=new Date();return[t,t]},semana:()=>{const t=new Date(),f=new Date();f.setDate(t.getDate()-6);return[f,t]},mes:()=>{const t=new Date();return[new Date(t.getFullYear(),t.getMonth(),1),t]},anterior:()=>{const t=new Date();return[new Date(t.getFullYear(),t.getMonth()-1,1),new Date(t.getFullYear(),t.getMonth(),0)]},anio:()=>{const t=new Date();return[new Date(t.getFullYear(),0,1),t]}}
const presetOptions=[{label:'Hoy',value:'hoy'},{label:'7 días',value:'semana'},{label:'Este mes',value:'mes'},{label:'Mes anterior',value:'anterior'},{label:'Este año',value:'anio'}]
const preset=ref('mes'),from=ref(ymd(presets.mes()[0])),to=ref(ymd(presets.mes()[1])),loading=ref(false)
const categories=ref([]),products=ref([]),user=ref(null),tableSearch=ref(''),productFilter=ref('')
const catalog=reactive({categorias:[],productos:[],usuarios:[]})
const emptyTotals={ventas:0,costo:0,descuento:0,ganancia:0,margen:0,unidades:0,cantidad_ventas:0,productos:0,ganancia_por_venta:0}
const data=reactive({periodo:{desde:null,hasta:null,granularidad:'dia'},indicadores:{...emptyTotals},anterior:{...emptyTotals},serie:[],productos:[],categorias:[]})
const money=v=>Number(v||0).toLocaleString('es-BO',{minimumFractionDigits:2,maximumFractionDigits:2})
const units=v=>Number(v||0).toLocaleString('es-BO',{maximumFractionDigits:3})
const shortMoney=v=>{const n=Number(v||0),a=Math.abs(n);return a>=1000?`${(n/1000).toFixed(a>=10000?0:1)}k`:n.toFixed(0)}
const photoUrl=p=>`${proxy.$imgBase}/images/${p}`
const longDate=v=>v?new Date(String(v).replace(' ','T')).toLocaleDateString('es-BO',{day:'2-digit',month:'short',year:'numeric'}):''
const dateTime=v=>v?new Date(String(v).replace(' ','T')).toLocaleString('es-BO',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}):''
const rangeLabel=computed(()=>from.value===to.value?longDate(from.value):`${longDate(from.value)} al ${longDate(to.value)}`)
const granularityLabel=computed(()=>({hora:'hora',dia:'día',mes:'mes'})[data.periodo.granularidad]||'día')
const chipStyle=c=>({background:c||'#eceff1',color:c?'#fff':'#455a64'})
const marginColor=v=>v<0?'negative':v<15?'orange':v<30?'amber-8':'positive',marginClass=v=>`text-${marginColor(v)} text-weight-medium`
const hasFilters=computed(()=>categories.value.length||products.value.length||user.value)
const filters=()=>({desde:from.value,hasta:to.value,categorias:categories.value,productos:products.value,user_id:user.value||undefined})
// axios manda los arreglos como categorias[]=1&categorias[]=2, que es lo que lee Laravel.
const delta=(cur,prev)=>prev?((cur-prev)/Math.abs(prev))*100:null
const kpis=computed(()=>{const i=data.indicadores,a=data.anterior,m=v=>money(v);return[
  {label:'Ganancia neta',value:i.ganancia,previous:a.ganancia,prefix:'Bs ',suffix:'',format:m,icon:'savings',color:'positive',delta:delta(i.ganancia,a.ganancia),main:true},
  {label:'Ventas',value:i.ventas,previous:a.ventas,prefix:'Bs ',suffix:'',format:m,icon:'payments',color:'primary',delta:delta(i.ventas,a.ventas)},
  {label:'Costo de lo vendido',value:i.costo,prefix:'Bs ',suffix:'',format:m,icon:'shopping_cart',color:'grey',caption:`Descuentos: Bs ${money(i.descuento)}`},
  {label:'Margen',value:i.margen,prefix:'',suffix:'%',format:v=>Number(v||0).toFixed(1),icon:'percent',color:'purple',caption:`Anterior: ${Number(a.margen||0).toFixed(1)}%`},
  {label:'Ganancia por venta',value:i.ganancia_por_venta,prefix:'Bs ',suffix:'',format:m,icon:'receipt_long',color:'deep-orange',caption:`${i.cantidad_ventas} ventas · ${units(i.unidades)} unidades`}
]})
const best=computed(()=>{const s=data.serie.filter(i=>i.ganancia>0);return s.length?s.reduce((b,i)=>i.ganancia>b.ganancia?i:b):null})
// Gráfico: la ganancia como área (protagonista), ingresos y costo como líneas finas de referencia.
const axisStyle={fontSize:'10px',colors:'#8a98a5'}
const baseChart={chart:{toolbar:{show:false},fontFamily:'Roboto, sans-serif',animations:{speed:400},zoom:{enabled:false}},dataLabels:{enabled:false},grid:{borderColor:'#eef1f4',strokeDashArray:3,padding:{top:0,right:8,bottom:0,left:8}}}
const trendSeries=computed(()=>[{name:'Ganancia',type:'area',data:data.serie.map(i=>i.ganancia)},{name:'Ventas',type:'line',data:data.serie.map(i=>i.ventas)},{name:'Costo',type:'line',data:data.serie.map(i=>i.costo)}])
const trendOptions=computed(()=>({...baseChart,
  chart:{...baseChart.chart,type:'line',events:{click:(e,ctx,cfg)=>{if(cfg.dataPointIndex>=0)openSlot(data.serie[cfg.dataPointIndex])},markerClick:(e,ctx,cfg)=>openSlot(data.serie[cfg.dataPointIndex])}},
  colors:['#1b8f4d','#f57c00','#90a4ae'],stroke:{curve:'smooth',width:[3,2,1.5],dashArray:[0,0,5]},
  fill:{type:['gradient','solid','solid'],gradient:{shadeIntensity:1,opacityFrom:.42,opacityTo:.04,stops:[0,95,100]}},
  markers:{size:data.serie.length>40?0:[4,0,0],strokeWidth:2,strokeColors:'#fff',hover:{size:6}},
  legend:{position:'top',horizontalAlign:'right',fontSize:'11px',itemMargin:{horizontal:6},markers:{width:8,height:8,radius:3}},
  xaxis:{categories:data.serie.map(i=>i.label),tickPlacement:'on',axisBorder:{show:false},axisTicks:{show:false},tooltip:{enabled:false},labels:{rotate:0,hideOverlappingLabels:true,style:axisStyle}},
  yaxis:{labels:{formatter:v=>`Bs ${shortMoney(v)}`,style:axisStyle}},
  annotations:{yaxis:[{y:0,borderColor:'#cfd8dc'}],points:best.value&&data.serie.length>1?[{x:best.value.label,y:best.value.ganancia,marker:{size:6,fillColor:'#ffb300',strokeColor:'#fff',strokeWidth:2},label:{text:`Bs ${shortMoney(best.value.ganancia)}`,borderColor:'#ffb300',offsetY:-4,style:{background:'#ffb300',color:'#fff',fontSize:'10px',fontWeight:700}}}]:[]},
  tooltip:{shared:true,intersect:false,y:{formatter:(v,{dataPointIndex:i})=>`Bs ${money(v)}`},custom:undefined,x:{formatter:(v,{dataPointIndex:i})=>{const s=data.serie[i];return s?`${s.label} · ${s.cantidad} ventas · margen ${s.ventas?(s.ganancia/s.ventas*100).toFixed(1):'0.0'}%`:v}}}
}))
const categorySeries=computed(()=>[{name:'Ganancia',data:data.categorias.map(c=>c.ganancia)}])
const categoryOptions=computed(()=>({...baseChart,chart:{...baseChart.chart,type:'bar',events:{dataPointSelection:(e,ctx,cfg)=>filterCategory(data.categorias[cfg.dataPointIndex])}},
  colors:[({value})=>value<0?'#e53935':'#1b8f4d'],plotOptions:{bar:{horizontal:true,borderRadius:4,barHeight:'64%',dataLabels:{position:'top'}}},
  dataLabels:{enabled:true,offsetX:22,style:{fontSize:'10px',colors:['#455a64']},formatter:v=>shortMoney(v)},
  xaxis:{categories:data.categorias.map(c=>c.nombre),labels:{formatter:v=>`Bs ${shortMoney(v)}`,style:axisStyle},axisBorder:{show:false},axisTicks:{show:false}},yaxis:{labels:{maxWidth:110,style:{...axisStyle,fontSize:'10.5px'}}},
  tooltip:{y:{formatter:(v,{dataPointIndex:i})=>{const c=data.categorias[i];return c?`Bs ${money(v)} · margen ${c.margen.toFixed(1)}% · ventas Bs ${money(c.ventas)}`:`Bs ${money(v)}`}}}}))
// Ranking: participación sobre la ganancia total y búsqueda local sobre lo ya calculado.
const productOptions=computed(()=>{const q=productFilter.value.toLowerCase();return catalog.productos.filter(p=>(!categories.value.length||categories.value.includes(p.categoria_id))&&(!q||`${p.nombre} ${p.codigo}`.toLowerCase().includes(q))).slice(0,80)})
const tableRows=computed(()=>{const total=data.productos.reduce((s,p)=>s+Math.max(0,p.ganancia),0),q=(tableSearch.value||'').toLowerCase();return data.productos.map((p,i)=>({...p,rank:i+1,participacion:total?Math.max(0,p.ganancia)/total*100:0})).filter(p=>!q||`${p.nombre} ${p.codigo} ${p.categoria}`.toLowerCase().includes(q))})
const sum=f=>tableRows.value.reduce((s,r)=>s+Number(r[f]||0),0)
const tablePagination=ref({rowsPerPage:25,sortBy:'ganancia',descending:true})
const columns=[{name:'rank',label:'#',field:'rank',align:'center',sortable:true},{name:'nombre',label:'Producto',field:'nombre',align:'left',sortable:true},{name:'num_ventas',label:'Ventas',field:'num_ventas',align:'center',sortable:true},{name:'cantidad',label:'Cantidad',field:'cantidad',align:'right',sortable:true,format:(v,r)=>`${units(v)} ${String(r.unidad||'').toUpperCase()==='KG'?'kg':'u.'}`},{name:'ventas',label:'Vendido',field:'ventas',align:'right',sortable:true,format:v=>`Bs ${money(v)}`},{name:'costo',label:'Costo',field:'costo',align:'right',sortable:true,format:v=>`Bs ${money(v)}`},{name:'ganancia',label:'Ganancia',field:'ganancia',align:'right',sortable:true},{name:'margen',label:'Margen',field:'margen',align:'right',sortable:true},{name:'participacion',label:'% de la ganancia',field:'participacion',align:'left',sortable:true},{name:'unitaria',label:'Gan. / unidad',field:r=>r.cantidad?r.ganancia/r.cantidad:0,align:'right',sortable:true,format:v=>`Bs ${money(v)}`},{name:'ver',label:'',field:'producto_id',align:'center'}]
function filterProducts(val,update){update(()=>{productFilter.value=val||''})}
function applyPreset(v){const[f,t]=presets[v]();from.value=ymd(f);to.value=ymd(t);load()}
function onDates(){preset.value=null;if(from.value&&to.value)load()}
function onCategories(){const ids=categories.value;if(ids.length)products.value=products.value.filter(id=>ids.includes(catalog.productos.find(p=>p.id===id)?.categoria_id));load()}
function filterCategory(c){const cat=c&&catalog.categorias.find(x=>x.nombre===c.nombre);if(!cat)return proxy.$alert.info('Esa categoría ya no existe en el catálogo');categories.value=[cat.id];onCategories()}
function clearFilters(){categories.value=[];products.value=[];user.value=null;load()}
function load(){loading.value=true;return proxy.$axios.get('/ganancias',{params:filters()}).then(r=>Object.assign(data,r.data)).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo cargar la ganancia')).finally(()=>{loading.value=false})}
// Detalle: todas las líneas vendidas detrás de una cifra, sea un producto del ranking o un punto del gráfico.
const detail=reactive({titulo:'',subtitulo:'',foto:null,producto_id:null,desde:null,hasta:null,rows:[],serie:[],granularidad:'dia',resumen:{...emptyTotals},loading:false,pagination:{page:1,rowsPerPage:50,rowsNumber:0}}),dialog=ref(false)
const detailKpis=computed(()=>{const r=detail.resumen;return[{label:'Ganancia',text:`Bs ${money(r.ganancia)}`,cls:r.ganancia<0?'text-negative':'text-positive'},{label:'Vendido',text:`Bs ${money(r.ventas)}`},{label:'Costo',text:`Bs ${money(r.costo)}`},{label:'Margen',text:`${Number(r.margen||0).toFixed(1)}%`,cls:marginClass(r.margen)},{label:detail.producto_id?'Cantidad':'Productos',text:detail.producto_id?units(r.unidades):r.productos},{label:'Ventas',text:r.cantidad_ventas}]})
const detailSeries=computed(()=>[{name:'Ganancia',data:detail.serie.map(i=>i.ganancia)},{name:'Ventas',data:detail.serie.map(i=>i.ventas)}])
const detailOptions=computed(()=>({...baseChart,chart:{...baseChart.chart,type:'area'},colors:['#1b8f4d','#f57c00'],stroke:{curve:'smooth',width:[2.5,1.5]},fill:{type:'gradient',gradient:{opacityFrom:[.35,.05],opacityTo:[.03,0]}},legend:{position:'top',horizontalAlign:'right',fontSize:'10px'},xaxis:{categories:detail.serie.map(i=>i.label),axisBorder:{show:false},axisTicks:{show:false},tooltip:{enabled:false},labels:{rotate:0,hideOverlappingLabels:true,style:axisStyle}},yaxis:{labels:{formatter:v=>`Bs ${shortMoney(v)}`,style:axisStyle}},tooltip:{shared:true,intersect:false,y:{formatter:v=>`Bs ${money(v)}`}}}))
const detailColumns=computed(()=>[{name:'fecha',label:'Fecha',field:'fecha',align:'left',format:dateTime},{name:'numero',label:'Venta',field:'numero',align:'left'},...(detail.producto_id?[]:[{name:'nombre',label:'Producto',field:'nombre',align:'left',classes:'ellipsis',style:'max-width:200px'}]),{name:'tipo_pago',label:'Pago',field:'tipo_pago',align:'center'},{name:'cantidad',label:'Cant.',field:'cantidad',align:'right',format:(v,r)=>`${units(v)}${String(r.unidad||'').toUpperCase()==='KG'?' kg':''}`},{name:'precio_compra',label:'P. compra',field:'precio_compra',align:'right',format:v=>money(v)},{name:'precio_venta',label:'P. venta',field:'precio_venta',align:'right',format:v=>money(v)},{name:'descuento',label:'Desc.',field:'descuento',align:'right',format:v=>Number(v)?`- ${money(v)}`:'—'},{name:'total',label:'Total',field:'total',align:'right',format:v=>`Bs ${money(v)}`},{name:'ganancia',label:'Ganancia',field:r=>Number(r.ganancia),align:'right'},{name:'margen',label:'Margen',field:r=>Number(r.total)?Number(r.ganancia)/Number(r.total)*100:0,align:'right'}])
function openProduct(row){Object.assign(detail,{titulo:row.nombre,subtitulo:`${row.codigo} · ${row.categoria||'Sin categoría'} · ${rangeLabel.value}`,foto:row.foto,producto_id:row.producto_id,desde:from.value,hasta:to.value,rows:[],serie:[],resumen:{...emptyTotals}});detail.pagination.page=1;dialog.value=true;loadDetail()}
function openSlot(s){if(!s)return;const d=String(s.desde).slice(0,10),h=data.periodo.granularidad==='hora'?` de ${s.desde.slice(11,16)} a ${s.hasta.slice(11,16)}`:'';Object.assign(detail,{titulo:`Ventas de ${data.periodo.granularidad==='mes'?s.label:longDate(d)}${h}`,subtitulo:`Bs ${money(s.ganancia)} de ganancia en ${s.cantidad} ventas`,foto:null,producto_id:null,desde:s.desde,hasta:s.hasta,rows:[],serie:[],resumen:{...emptyTotals}});detail.pagination.page=1;dialog.value=true;loadDetail()}
function loadDetail(req){const p=req?.pagination||detail.pagination;detail.loading=true
  return proxy.$axios.get('/ganancias-ventas',{params:{...filters(),desde:detail.desde,hasta:detail.hasta,producto_id:detail.producto_id||undefined,page:p.page,per_page:p.rowsPerPage}})
    .then(({data:r})=>{detail.rows=r.lineas.data;detail.resumen=r.resumen;detail.serie=r.serie;detail.pagination={...p,rowsNumber:r.lineas.total}})
    .catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudieron cargar las ventas')).finally(()=>{detail.loading=false})}
function exportCsv(){const head=['#','Código','Producto','Categoría','Unidad','Ventas','Cantidad','Vendido','Costo','Descuento','Ganancia','Margen %','% de la ganancia'],esc=v=>`"${String(v??'').replace(/"/g,'""')}"`
  const lines=tableRows.value.map(r=>[r.rank,r.codigo,r.nombre,r.categoria,r.unidad,r.num_ventas,r.cantidad,r.ventas,r.costo,r.descuento,r.ganancia,r.margen.toFixed(2),r.participacion.toFixed(2)])
  const csv='﻿'+[[`Ganancias ${rangeLabel.value}`],head,...lines].map(l=>l.map(esc).join(';')).join('\r\n'),url=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8'})),a=document.createElement('a');a.href=url;a.download=`ganancias_${from.value}_${to.value}.csv`;a.click();URL.revokeObjectURL(url)}
onMounted(()=>{proxy.$axios.get('/ganancias-catalogos').then(r=>Object.assign(catalog,r.data)).catch(()=>{});load()})
</script>

<style scoped>
.ganancias{background:linear-gradient(180deg,#eef8f1 0,#f8faf9 260px)}
.hero{display:flex;align-items:center;flex-wrap:wrap;gap:8px;padding:10px 14px;border-radius:12px;color:#fff;background:linear-gradient(120deg,#1f2a24,#1b8f4d 55%,#66bb6a);box-shadow:0 6px 18px rgba(27,143,77,.22)}.hero-subtitle{color:rgba(255,255,255,.82)}
.period-toggle{border:1px solid rgba(255,255,255,.45);border-radius:8px;font-size:11px}
.range-dates :deep(.q-field){width:140px;font-size:11px}.range-dates :deep(.q-field__control){height:32px;background:rgba(255,255,255,.14)}
.panel,.kpi-card{border-radius:10px;background:rgba(255,255,255,.97)}
.kpi-grid{display:grid;grid-template-columns:1.25fr repeat(4,minmax(0,1fr));gap:6px}.kpi-main{border-color:#a5d6a7;background:linear-gradient(135deg,#fff,#f1faf3)}.kpi-main .kpi-value{font-size:20px;color:#1b8f4d}
.kpi-icon{color:#fff}.kpi-positive{background:linear-gradient(135deg,#1b8f4d,#4caf50)}.kpi-primary{background:linear-gradient(135deg,#f57c00,#ffb300)}.kpi-grey{background:linear-gradient(135deg,#546e7a,#90a4ae)}.kpi-purple{background:linear-gradient(135deg,#6a1b9a,#ab47bc)}.kpi-deep-orange{background:linear-gradient(135deg,#e65100,#ff9800)}
.kpi-label{font-size:10px;line-height:13px;color:#78909c;text-transform:uppercase;letter-spacing:.3px}.kpi-value{font-size:17px;font-weight:700;line-height:22px;white-space:nowrap}.kpi-caption{font-size:10px;line-height:13px;white-space:nowrap;overflow:hidden}
.card-title{font-size:12.5px;font-weight:700;line-height:16px}.card-sub{font-size:10px;line-height:13px;color:#8a98a5}
.best-chip{font-size:10.5px;padding:3px 8px;border-radius:12px;background:#fff8e1;color:#6d4c00;border:1px solid #ffe082}
.dot{width:10px;height:10px;border-radius:50%;display:inline-block}
.empty{text-align:center;color:#9e9e9e;padding:40px 0;font-size:12px}
.rank-table :deep(tbody tr){cursor:pointer}.rank-table :deep(tbody tr:hover){background:#f1faf3}.product-name{max-width:260px;font-size:12px}
.rank{width:22px;height:22px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;background:#eceff1;color:#546e7a}.rank-1{background:#ffb300;color:#fff}.rank-2{background:#90a4ae;color:#fff}.rank-3{background:#bf8a5a;color:#fff}
.margin-cell{min-width:70px;text-align:right}.margin-cell span{font-size:11px}
.share-cell{position:relative;min-width:110px;height:18px;background:#f1f4f3;border-radius:4px;overflow:hidden}.share-bar{position:absolute;inset:0 auto 0 0;background:linear-gradient(90deg,#a5d6a7,#66bb6a);border-radius:4px}.share-cell span{position:relative;font-size:10.5px;padding-left:6px;line-height:18px;font-weight:600;color:#1b5e20}
.total-row{background:#f5f7f6}.total-row td{font-size:12px}
.detail-card{width:1100px;max-width:97vw}.detail-head{background:linear-gradient(120deg,#1f2a24,#1b8f4d);color:#fff}.detail-head .text-caption{color:rgba(255,255,255,.8)}
.mini-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:6px}.mini-kpi{border:1px solid #e6ece9;border-radius:8px;padding:6px 8px;background:#fafcfb}.mini-value{font-size:15px;font-weight:700}
.detail-table :deep(th){font-weight:700;background:#f5f7f6}
@media(max-width:1100px){.kpi-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.kpi-main{grid-column:span 3}}
@media(max-width:700px){.kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.kpi-main{grid-column:span 2}.mini-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.hero{padding:10px}}
</style>
