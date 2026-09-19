<template>
  <q-page class="dashboard q-pa-sm">
    <template v-if="canSeeStats">
      <div class="hero q-mb-sm">
        <div class="col-grow"><div class="text-h6 text-weight-bold">Hola, {{ $store.user.name || $store.user.username }}</div><div class="text-caption hero-subtitle">{{data.periodo.titulo}}<span v-if="rangeLabel"> · {{rangeLabel}}</span></div></div>
        <q-btn-toggle v-model="period" :options="periodOptions" no-caps dense unelevated toggle-color="white" toggle-text-color="primary" color="transparent" text-color="white" class="period-toggle" @update:model-value="onPeriod"/>
        <div v-if="period==='rango'" class="row items-center no-wrap q-gutter-xs range-dates"><q-input v-model="from" type="date" dense outlined dark bg-color="transparent" label="Desde" @update:model-value="loadDashboard"/><q-input v-model="to" type="date" dense outlined dark bg-color="transparent" label="Hasta" @update:model-value="loadDashboard"/></div>
        <q-btn v-if="$store.hasPermission('Crear Ventas')" unelevated dense color="white" text-color="primary" icon="point_of_sale" label="Nueva venta" no-caps class="q-px-sm" to="/ventas/nueva"/>
      </div>

      <div class="kpi-grid q-mb-sm">
        <q-card v-for="item in kpis" :key="item.label" flat bordered class="kpi-card">
          <q-card-section class="row items-center q-pa-xs">
            <q-avatar :class="`kpi-icon kpi-${item.color}`" :icon="item.icon" size="34px"/>
            <div class="q-ml-sm col"><div class="kpi-label text-grey-7">{{item.label}}</div><div class="kpi-value">{{item.money?'Bs ':''}}{{item.money?money(item.value):item.value}}</div><div class="kpi-caption" :class="`text-${item.color}`">{{item.caption}}</div></div>
          </q-card-section>
        </q-card>
      </div>

      <div class="row q-col-gutter-sm q-mb-sm">
        <div class="col-12 col-lg-8"><q-card flat bordered class="chart-card">
          <q-card-section class="row items-center q-py-xs q-px-sm"><div><div class="card-title">Evolución de ventas</div><div class="card-sub">{{data.periodo.titulo}} · ingresos y cantidad de ventas</div></div><q-space/><q-btn dense flat round size="sm" icon="refresh" :loading="loading" @click="loadDashboard"/></q-card-section>
          <q-card-section class="q-pa-none"><apexchart type="line" height="238" :options="trendOptions" :series="trendSeries"/></q-card-section>
        </q-card></div>
        <div class="col-12 col-lg-4"><q-card flat bordered class="chart-card">
          <q-card-section class="q-py-xs q-px-sm"><div class="card-title">Formas de pago</div><div class="card-sub">Distribución del total vendido</div></q-card-section>
          <q-card-section class="q-pa-none"><apexchart type="donut" height="238" :options="paymentOptions" :series="paymentSeries"/></q-card-section>
        </q-card></div>
      </div>

      <div class="row q-col-gutter-sm">
        <div class="col-12 col-lg-7"><q-card flat bordered class="chart-card">
          <q-card-section class="q-py-xs q-px-sm"><div class="card-title">Ventas por usuario</div><div class="card-sub">Total vendido por cada cajero</div></q-card-section>
          <q-card-section class="q-pa-none"><apexchart type="bar" height="252" :options="userOptions" :series="userSeries"/></q-card-section>
        </q-card></div>
        <div class="col-12 col-lg-5"><q-card flat bordered class="chart-card"><q-card-section class="row items-center q-py-xs q-px-sm"><div><div class="card-title">Más vendidos</div><div class="card-sub">{{topMode==='productos'?'Por producto':'Por categoría'}} · cantidad y ganancia</div></div><q-space/><q-btn-toggle v-model="topMode" :options="topOptions" no-caps dense unelevated size="sm" toggle-color="primary" color="grey-3" text-color="grey-8" class="top-toggle q-mr-xs"/><q-btn dense flat size="sm" icon="inventory_2" color="primary" to="/productos"/></q-card-section><q-separator/>
          <q-list separator class="top-list"><q-item v-for="(item,index) in topRows" :key="item.producto_id||item.nombre" dense class="q-px-sm">
            <q-item-section avatar class="top-thumb"><div class="rank">{{index+1}}</div><q-avatar rounded size="32px" color="grey-2"><img v-if="item.foto" :src="photoUrl(item.foto)"/><q-icon v-else :name="topMode==='productos'?'inventory_2':'category'" size="16px" color="grey-5"/></q-avatar></q-item-section>
            <q-item-section><q-item-label lines="1" class="text-caption text-weight-bold">{{item.nombre}}</q-item-label><q-item-label caption class="top-meta">Bs {{money(item.total)}} vendidos · <span class="text-positive">Bs {{money(item.ganancia)}} de ganancia</span></q-item-label></q-item-section>
            <q-item-section side><q-badge color="primary" :label="`${units(item.cantidad)} uds.`"/></q-item-section>
          </q-item><q-item v-if="!topRows.length"><q-item-section class="text-center text-grey-6 q-py-lg">Sin ventas en este periodo</q-item-section></q-item></q-list>
        </q-card></div>
      </div>
    </template>

    <!-- Sin permiso "Ver Estadísticas": solo la identidad de la empresa, ningún importe ni gráfico. -->
    <div v-else class="brand-screen">
      <div class="brand-card">
        <img v-if="company.logo_url" :src="company.logo_url" class="brand-logo" alt="Logo"/>
        <q-icon v-else name="storefront" size="96px" color="primary" class="q-mb-sm"/>
        <div class="text-h5 text-weight-bold">{{company.nombre_empresa||'Bean'}}</div>
        <div class="text-body2 text-grey-7 q-mt-xs">{{company.direccion||'Dirección no registrada'}}</div>
        <div class="text-caption text-grey-6 q-mt-xs">Tel. {{company.telefono||'—'}}<span v-if="company.nit"> · NIT {{company.nit}}</span></div>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, onMounted, reactive, ref } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
const apexchart=VueApexCharts
const {proxy}=getCurrentInstance()
const data=reactive({periodo:{clave:'semana',titulo:'Últimos 7 días',desde:null,hasta:null,granularidad:'dia'},indicadores:{ventas:0,ganancia:0,productos:0,cantidad_ventas:0,ticket_promedio:0},diario:[],usuarios:[],pagos:[],productos_top:[],categorias_top:[]})
const canSeeStats=computed(()=>proxy.$store.hasPermission('Ver Estadísticas'))
const company=ref(JSON.parse(localStorage.getItem('empresaBean')||'{}'))
// El panel arranca en la semana; el backend recorta al periodo elegido todos los indicadores, no sólo la serie.
// "Rango" deja elegir las fechas a mano; el backend recibe periodo=rango&desde=&hasta= y decide la granularidad.
const today=()=>{const d=new Date();return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`}
const period=ref('semana'),loading=ref(false),from=ref(today()),to=ref(today())
const periodOptions=[{label:'Hoy',value:'hoy'},{label:'Ayer',value:'ayer'},{label:'Semana',value:'semana'},{label:'Mes',value:'mes'},{label:'Año',value:'anio'},{label:'Rango',value:'rango'}]
// El ranking se mira por producto o por categoría; ambos vienen ya calculados con su ganancia.
const topMode=ref('productos'),topOptions=[{label:'Productos',value:'productos'},{label:'Categorías',value:'categorias'}]
const topRows=computed(()=>topMode.value==='productos'?data.productos_top:data.categorias_top)
const money=v=>Number(v||0).toLocaleString('es-BO',{minimumFractionDigits:2,maximumFractionDigits:2})
const units=v=>Number(v||0).toLocaleString('es-BO',{maximumFractionDigits:3})
const shortMoney=v=>{const n=Number(v||0);return n>=1000?`${(n/1000).toFixed(n>=10000?0:1)}k`:n.toFixed(0)}
const photoUrl=path=>`${proxy.$imgBase}/images/${path}`
const shortDate=v=>v?new Date(String(v).replace(' ','T')).toLocaleDateString('es-BO',{day:'2-digit',month:'2-digit'}):''
const rangeLabel=computed(()=>data.periodo.desde?`${shortDate(data.periodo.desde)} al ${shortDate(data.periodo.hasta)}`:'')
const kpis=computed(()=>[
  {label:'Ventas del periodo',value:data.indicadores.ventas,money:true,icon:'payments',color:'primary',caption:`${data.indicadores.cantidad_ventas} ventas`},
  {label:'Ganancia estimada',value:data.indicadores.ganancia,money:true,icon:'trending_up',color:'positive',caption:'Ventas menos costo y descuento'},
  {label:'Productos vendidos',value:data.indicadores.productos,money:false,icon:'inventory_2',color:'deep-orange',caption:'Unidades acumuladas'},
  {label:'Ticket promedio',value:data.indicadores.ticket_promedio,money:true,icon:'receipt_long',color:'purple',caption:'Promedio por venta'}
])
const axisStyle={fontSize:'10px',colors:'#8a98a5'}
const baseChart={chart:{toolbar:{show:false},fontFamily:'Roboto, sans-serif',animations:{speed:400}},dataLabels:{enabled:false},grid:{borderColor:'#eef1f4',strokeDashArray:3,padding:{top:0,right:6,bottom:0,left:6}},tooltip:{theme:'light'}}
// Montaña (ingresos en Bs) + histograma (número de ventas) sobre el mismo eje de tiempo.
const trendSeries=computed(()=>[
  {name:'Ingresos',type:'area',data:data.diario.map(i=>Number(i.total||0))},
  {name:'Ventas',type:'column',data:data.diario.map(i=>Number(i.cantidad||0))}
])
const trendOptions=computed(()=>({...baseChart,
  chart:{...baseChart.chart,type:'line',stacked:false,zoom:{enabled:false}},
  colors:['#f57c00','#b0bec5'],
  stroke:{curve:'smooth',width:[2.5,0]},
  fill:{type:['gradient','solid'],gradient:{shadeIntensity:1,opacityFrom:.45,opacityTo:.03,stops:[0,92,100]},opacity:[1,.55]},
  plotOptions:{bar:{columnWidth:data.diario.length>16?'70%':'38%',borderRadius:2}},
  markers:{size:0,strokeWidth:0,hover:{size:5}},
  legend:{position:'top',horizontalAlign:'right',fontSize:'11px',offsetY:2,itemMargin:{horizontal:6},markers:{width:8,height:8,radius:3}},
  xaxis:{categories:data.diario.map(i=>i.label),tickPlacement:'on',axisBorder:{show:false},axisTicks:{show:false},tooltip:{enabled:false},labels:{rotate:0,hideOverlappingLabels:true,style:axisStyle}},
  yaxis:[{labels:{formatter:v=>`Bs ${shortMoney(v)}`,style:axisStyle}},
         {opposite:true,min:0,forceNiceScale:true,labels:{formatter:v=>Math.round(v),style:axisStyle}}],
  tooltip:{shared:true,intersect:false,y:[{formatter:v=>`Bs ${money(v)}`},{formatter:v=>`${Math.round(v)} ventas`}]}
}))
const paymentSeries=computed(()=>data.pagos.map(i=>Number(i.total)))
const paymentOptions=computed(()=>({...baseChart,labels:data.pagos.map(i=>i.nombre),colors:['#21ba45','#2196f3','#9c27b0'],legend:{position:'bottom',fontSize:'11px',itemMargin:{horizontal:6}},stroke:{width:0},plotOptions:{pie:{donut:{size:'70%',labels:{show:true,value:{fontSize:'16px',fontWeight:700,formatter:v=>`Bs ${money(v)}`},total:{show:true,label:'Total',fontSize:'11px',formatter:()=>`Bs ${money(data.indicadores.ventas)}`}}}}},tooltip:{y:{formatter:v=>`Bs ${money(v)}`}}}))
const userSeries=computed(()=>[{name:'Total vendido',data:data.usuarios.map(i=>Number(i.total))}])
const userOptions=computed(()=>({...baseChart,chart:{...baseChart.chart,type:'bar'},colors:['#fb8c00'],plotOptions:{bar:{borderRadius:4,horizontal:true,barHeight:'62%'}},dataLabels:{enabled:true,style:{fontSize:'10px',colors:['#fff']},formatter:v=>`Bs ${shortMoney(v)}`},xaxis:{categories:data.usuarios.map(i=>i.nombre),labels:{formatter:v=>`Bs ${shortMoney(v)}`,style:axisStyle},axisBorder:{show:false},axisTicks:{show:false}},yaxis:{labels:{style:axisStyle}},tooltip:{y:{formatter:v=>`Bs ${money(v)}`}}}))
function onPeriod(){if(period.value!=='rango')return loadDashboard();if(from.value&&to.value)loadDashboard()}
function loadDashboard(){
  loading.value=true
  const params=period.value==='rango'?{periodo:'rango',desde:from.value,hasta:to.value}:{periodo:period.value}
  return proxy.$axios.get('/dashboard',{params})
    .then(r=>Object.assign(data,r.data))
    .catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo cargar el panel'))
    .finally(()=>{loading.value=false})
}
onMounted(()=>{if(canSeeStats.value)return loadDashboard()
  // Sin permiso no se pide /dashboard (respondería 403); se refresca la ficha de la empresa para el logo.
  proxy.$axios.get('/configuracion').then(({data:info})=>{info.logo_url=info.logo?`${proxy.$imgBase}/images/${info.logo}`:null;company.value=info;localStorage.setItem('empresaBean',JSON.stringify(info))}).catch(()=>{})})
</script>

<style scoped>
.dashboard{background:linear-gradient(180deg,#fff4f3 0,#faf8f8 260px)}
.hero{display:flex;align-items:center;flex-wrap:wrap;gap:8px;padding:10px 14px;border-radius:12px;color:#fff;background:linear-gradient(120deg,#222222,#f57c00 60%,#ffb300);box-shadow:0 6px 18px rgba(183,28,28,.20)}.hero-subtitle{color:rgba(255,255,255,.82)}
.period-toggle{border:1px solid rgba(255,255,255,.45);border-radius:8px;font-size:11px}
.range-dates :deep(.q-field){width:140px;font-size:11px}.range-dates :deep(.q-field__control){height:32px;background:rgba(255,255,255,.14)}
.kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px}.kpi-card,.chart-card{border-radius:10px;background:rgba(255,255,255,.96)}
.kpi-icon{color:#fff}.kpi-primary{background:linear-gradient(135deg,#f57c00,#ffb300)}.kpi-positive{background:linear-gradient(135deg,#1b8f4d,#4caf50)}.kpi-deep-orange{background:linear-gradient(135deg,#e65100,#ff9800)}.kpi-purple{background:linear-gradient(135deg,#6a1b9a,#ab47bc)}
.kpi-label{font-size:10px;line-height:13px}.kpi-value{font-size:17px;font-weight:700;line-height:20px}.kpi-caption{font-size:9.5px;line-height:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.card-title{font-size:12.5px;font-weight:700;line-height:16px}.card-sub{font-size:10px;line-height:13px;color:#8a98a5}
.brand-screen{display:flex;align-items:center;justify-content:center;min-height:calc(100vh - 100px)}.brand-card{text-align:center;padding:38px 34px;border-radius:16px;background:rgba(255,255,255,.96);border:1px solid #ffe0b2;box-shadow:0 8px 26px rgba(183,28,28,.10);max-width:420px}.brand-logo{width:150px;max-height:150px;object-fit:contain;margin-bottom:14px}
.top-toggle{border:1px solid #e0e0e0;border-radius:8px;font-size:10px}
.top-list{max-height:252px;overflow:auto}.top-thumb{min-width:40px;padding-right:6px}.top-meta{font-size:10px;line-height:13px}
.rank{position:absolute;margin-left:-6px;margin-top:-6px;width:16px;height:16px;border-radius:50%;background:#f57c00;color:white;font-size:9px;display:flex;align-items:center;justify-content:center;z-index:1}
@media(max-width:900px){.kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:500px){.hero{padding:10px}.kpi-grid{grid-template-columns:1fr}}
</style>
