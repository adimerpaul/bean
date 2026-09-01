<template>
  <q-page class="shop">
    <!-- Cabecera fija: marca, buscador, compartir y carrito -->
    <header class="bar">
      <div class="bar__inner">
        <div class="brand">
          <q-avatar square size="36px" class="brand__logo"><img v-if="logoUrl" :src="logoUrl" alt=""/><q-icon v-else name="fa-solid fa-store" color="amber-6" size="20px"/></q-avatar>
          <div class="brand__text">
            <div class="brand__name">{{tienda.empresa.nombre||'Tienda'}}</div>
            <div class="brand__sub"><q-icon name="fa-solid fa-bolt" size="11px"/> Pedidos por WhatsApp</div>
          </div>
        </div>
        <q-input v-model="search" dense outlined dark class="bar__search" placeholder="Buscar producto…" clearable @update:model-value="onSearch">
          <template #prepend><q-icon name="fa-solid fa-magnifying-glass" color="amber-6"/></template>
        </q-input>
        <q-btn flat round dense color="amber-6" icon="fa-solid fa-share-nodes" @click="share"><q-tooltip>Compartir la tienda</q-tooltip></q-btn>
        <q-btn v-if="isStaff" flat dense no-caps color="amber-6" icon="fa-solid fa-gauge-high" label="Menú" class="bar__back gt-sm" to="/"><q-tooltip>Volver al sistema</q-tooltip></q-btn>
        <q-btn unelevated no-caps class="bar__cart" icon="fa-solid fa-cart-shopping" :label="cartCount?String(cartCount):''" @click="cartDialog=true"><q-tooltip>Ver mi pedido</q-tooltip></q-btn>
      </div>
    </header>

    <!-- Portada estilo carta -->
    <section class="hero">
      <div class="hero__inner">
        <div class="hero__left">
          <div class="hero__kicker"><span class="dot"/><span class="dot"/><span class="dot"/> CATÁLOGO EN LÍNEA</div>
          <h1 class="hero__title">{{tienda.empresa.nombre||'Nuestra tienda'}}</h1>
          <p class="hero__text">Elegí lo que necesitás, armá tu pedido y mandanoslo por WhatsApp. Te confirmamos precio final y entrega.</p>
          <div class="hero__chips">
            <span class="pill"><q-icon name="fa-solid fa-box" size="14px"/>{{tienda.total_productos}} productos</span>
            <span class="pill"><q-icon name="fa-solid fa-truck-fast" size="14px"/>Delivery o recojo</span>
            <span class="pill"><q-icon name="fa-solid fa-qrcode" size="14px"/>Efectivo o QR</span>
            <span v-if="tienda.empresa.direccion" class="pill"><q-icon name="fa-solid fa-location-dot" size="14px"/>{{tienda.empresa.direccion}}</span>
            <span v-if="tienda.empresa.telefono" class="pill"><q-icon name="fa-solid fa-phone" size="14px"/>{{tienda.empresa.telefono}}</span>
          </div>
        </div>
        <div class="hero__right">
          <div class="hero__ring">
            <q-icon name="fa-solid fa-basket-shopping" size="52px" color="amber-6"/>
            <div class="hero__badge"><b>{{prettyPhone}}</b><span>PEDIDOS</span></div>
          </div>
        </div>
      </div>
    </section>

    <!-- Filtros -->
    <div class="filters">
      <div class="filters__chips">
        <button class="cat" :class="{'cat--on':!category}" @click="selectCategory(null)"><q-icon name="fa-solid fa-border-all" size="14px"/>Todo</button>
        <button v-for="c in tienda.categorias" :key="c.id" class="cat" :class="{'cat--on':category===c.id}" @click="selectCategory(c.id)">
          <q-icon :name="categoryIcon(c.nombre)" size="14px"/>{{c.nombre}}
        </button>
      </div>
      <div class="filters__right">
        <q-toggle v-model="onlyAvailable" dense size="xs" color="amber-6" dark label="Con stock" class="filters__toggle" @update:model-value="reload"/>
        <q-select v-model="order" :options="orderOptions" emit-value map-options dense outlined dark options-dense class="filters__order" @update:model-value="reload">
          <template #prepend><q-icon name="fa-solid fa-arrow-down-wide-short" size="16px" color="amber-6"/></template>
        </q-select>
      </div>
    </div>

    <!-- Vitrina -->
    <div class="wrap">
      <div v-if="loading&&!products.length" class="grid">
        <div v-for="n in 12" :key="n" class="card"><q-skeleton height="120px" square dark/><div class="q-pa-sm"><q-skeleton type="text" dark/><q-skeleton type="text" width="60%" dark/></div></div>
      </div>
      <div v-else-if="!products.length" class="empty"><q-icon name="fa-solid fa-magnifying-glass-minus" size="46px" color="amber-6"/><div>No encontramos productos con esa búsqueda</div><q-btn flat no-caps color="amber-6" icon="fa-solid fa-rotate-left" label="Ver todo" @click="clearFilters"/></div>
      <div v-else class="grid">
        <article v-for="p in products" :key="p.id" class="card" :class="{'card--off':!p.disponible,'card--in':inCart(p.id)}">
          <div class="card__img" @click="preview(p)">
            <img v-if="p.foto" :src="photo(p.foto)" :alt="p.nombre" loading="lazy"/>
            <q-icon v-else name="fa-solid fa-box" size="32px" color="grey-8"/>
            <span class="card__price">Bs {{money(p.precio_venta)}}</span>
            <span v-if="!p.disponible" class="card__flag"><q-icon name="fa-solid fa-ban" size="11px"/> Sin stock</span>
            <span v-else-if="inCart(p.id)" class="card__flag card__flag--on"><q-icon name="fa-solid fa-circle-check" size="11px"/> En el pedido</span>
          </div>
          <div class="card__body">
            <div class="card__cat"><q-icon :name="categoryIcon(p.categoria)" size="11px"/>{{p.categoria||'—'}}</div>
            <div class="card__name" :title="p.nombre">{{p.nombre}}</div>
            <div class="card__unit"><q-icon :name="p.unidad==='KG'?'fa-solid fa-weight-scale':'fa-solid fa-box'" size="11px"/>por {{p.unidad}}</div>
          </div>
          <div class="card__foot">
            <button v-if="!inCart(p.id)" class="btn-add" :disabled="!p.disponible" @click="add(p)"><q-icon name="fa-solid fa-cart-plus" size="14px"/>Agregar</button>
            <div v-else class="stepper">
              <q-btn dense flat round size="sm" icon="fa-solid fa-minus" color="amber-6" @click="step(p.id,-1)"/>
              <input v-model.number="cartItem(p.id).cantidad" class="stepper__input" type="number" :step="p.unidad==='KG'?0.5:1" min="0" @change="normalize(p.id)">
              <span class="stepper__unit">{{p.unidad}}</span>
              <q-btn dense flat round size="sm" icon="fa-solid fa-plus" color="amber-6" @click="step(p.id,1)"/>
            </div>
          </div>
        </article>
      </div>
      <div v-if="page<lastPage" class="more"><q-btn outline no-caps color="amber-6" icon="fa-solid fa-chevron-down" label="Ver más productos" :loading="loading" @click="loadMore"/></div>
    </div>

    <footer class="foot">
      <div class="foot__brand"><q-icon name="fa-solid fa-store" size="18px" color="amber-6"/><b>{{tienda.empresa.nombre||'Tienda'}}</b></div>
      <div class="foot__data">
        <span v-if="tienda.empresa.direccion"><q-icon name="fa-solid fa-location-dot" size="13px"/>{{tienda.empresa.direccion}}</span>
        <span v-if="tienda.empresa.telefono"><q-icon name="fa-solid fa-phone" size="13px"/>{{tienda.empresa.telefono}}</span>
        <span><q-icon name="fa-solid fa-clock" size="13px"/>Atención de lunes a sábado</span>
      </div>
      <div class="foot__social">
        <q-btn round unelevated size="sm" class="social social--wa" icon="fa-brands fa-whatsapp" @click="chatWhatsapp"><q-tooltip>Escribinos por WhatsApp</q-tooltip></q-btn>
        <q-btn round unelevated size="sm" class="social" icon="fa-solid fa-phone" :href="`tel:${tienda.empresa.telefono||''}`"><q-tooltip>Llamar</q-tooltip></q-btn>
        <q-btn round unelevated size="sm" class="social" icon="fa-solid fa-share-nodes" @click="share"><q-tooltip>Compartir</q-tooltip></q-btn>
      </div>
      <div class="foot__legal">Los precios pueden variar según el peso final de cada producto · Pedidos al {{prettyPhone}}</div>
    </footer>

    <!-- Acciones flotantes: pedido y WhatsApp (abajo a la derecha) -->
    <div class="fabs">
      <q-btn v-if="cartCount" round unelevated class="fab fab--cart gt-xs" icon="fa-solid fa-cart-shopping" @click="cartDialog=true">
        <q-badge floating class="fab__badge">{{cartCount}}</q-badge>
        <q-tooltip anchor="center left" self="center right">Ver mi pedido · Bs {{money(cartTotal)}}</q-tooltip>
      </q-btn>
      <q-btn round unelevated class="fab fab--wa" @click="chatWhatsapp">
        <q-icon name="fa-brands fa-whatsapp" size="27px"/>
        <q-tooltip anchor="center left" self="center right">Escribinos por WhatsApp</q-tooltip>
      </q-btn>
    </div>

    <!-- Barra de pedido en móvil -->
    <div v-if="cartCount" class="mobar lt-sm" @click="cartDialog=true">
      <div><span class="mobar__count">{{cartCount}} producto{{cartCount===1?'':'s'}}</span><div class="mobar__total">Bs {{money(cartTotal)}}</div></div>
      <q-space/>
      <span class="mobar__cta"><q-icon name="fa-solid fa-cart-shopping" size="16px"/> Ver pedido</span>
    </div>

    <!-- Pedido -->
    <q-dialog v-model="cartDialog" :maximized="$q.screen.lt.sm" position="right" full-height>
      <q-card class="cart column no-wrap">
        <q-card-section class="row items-center cart__head">
          <q-icon name="fa-solid fa-receipt" size="22px" class="q-mr-sm"/>
          <div><div class="text-subtitle1 text-weight-bold">Mi pedido</div><div class="text-caption">{{cartCount}} producto{{cartCount===1?'':'s'}} · {{tienda.empresa.nombre}}</div></div>
          <q-space/><q-btn flat round dense icon="fa-solid fa-xmark" v-close-popup/>
        </q-card-section>

        <div class="col scroll cart__body">
          <div v-if="!cart.length" class="empty"><q-icon name="fa-solid fa-cart-arrow-down" size="42px" color="grey-6"/><div>Tu pedido está vacío</div></div>
          <q-list v-else separator dark>
            <q-item v-for="item in cart" :key="item.id" class="q-py-sm">
              <q-item-section avatar><q-avatar square size="46px" class="cart__thumb"><img v-if="item.foto" :src="photo(item.foto)" alt=""/><q-icon v-else name="fa-solid fa-box" color="grey-6"/></q-avatar></q-item-section>
              <q-item-section>
                <q-item-label class="text-weight-medium">{{item.nombre}}</q-item-label>
                <q-item-label caption class="text-amber-6">Bs {{money(item.precio_venta)}} / {{item.unidad}}</q-item-label>
                <div class="stepper q-mt-xs">
                  <q-btn dense flat round size="sm" icon="fa-solid fa-minus" color="amber-6" @click="step(item.id,-1)"/>
                  <input v-model.number="item.cantidad" class="stepper__input" type="number" :step="item.unidad==='KG'?0.5:1" min="0" @change="normalize(item.id)">
                  <span class="stepper__unit">{{item.unidad}}</span>
                  <q-btn dense flat round size="sm" icon="fa-solid fa-plus" color="amber-6" @click="step(item.id,1)"/>
                </div>
              </q-item-section>
              <q-item-section side top>
                <div class="text-weight-bold text-amber-6">Bs {{money(item.cantidad*item.precio_venta)}}</div>
                <q-btn dense flat round size="sm" icon="fa-solid fa-trash" color="grey-6" @click="remove(item.id)"/>
              </q-item-section>
            </q-item>
          </q-list>

          <div v-if="cart.length" class="q-pa-sm q-gutter-sm">
            <div class="cart__section"><q-icon name="fa-solid fa-user" size="14px"/>Tus datos</div>
            <q-input v-model="client.nombre" dense outlined dark label="Tu nombre *" maxlength="80"><template #prepend><q-icon name="fa-solid fa-id-card" size="18px"/></template></q-input>
            <q-input v-model="client.telefono" dense outlined dark label="Tu teléfono" mask="########" hint="Opcional, para coordinar la entrega"><template #prepend><q-icon name="fa-solid fa-phone" size="18px"/></template></q-input>
            <div class="cart__section"><q-icon name="fa-solid fa-truck-fast" size="14px"/>Entrega</div>
            <q-btn-toggle v-model="client.entrega" spread no-caps unelevated toggle-color="amber-7" toggle-text-color="black" color="grey-9" text-color="grey-4"
                          :options="[{label:'Recojo en tienda',value:'RECOJO',icon:'fa-solid fa-store'},{label:'Delivery',value:'DELIVERY',icon:'fa-solid fa-motorcycle'}]"/>
            <q-input v-if="client.entrega==='DELIVERY'" v-model="client.direccion" dense outlined dark label="Dirección de entrega *" autogrow><template #prepend><q-icon name="fa-solid fa-location-dot" size="18px"/></template></q-input>
            <q-input v-model="client.nota" dense outlined dark label="Nota para la tienda" autogrow placeholder="Ej. cortar el pollo en presas"><template #prepend><q-icon name="fa-solid fa-pen-to-square" size="18px"/></template></q-input>
          </div>
        </div>

        <q-card-section class="cart__foot">
          <div class="row items-center"><span class="text-grey-5">Total referencial</span><q-space/><span class="text-h6 text-weight-bold text-amber-6">Bs {{money(cartTotal)}}</span></div>
          <div class="text-caption text-grey-6 q-mb-sm">El precio final se confirma con la tienda según el peso de cada producto.</div>
          <q-btn class="full-width btn-wa" unelevated no-caps size="md" icon="fa-brands fa-whatsapp" label="Enviar pedido por WhatsApp" :disable="!cart.length" @click="sendWhatsapp"/>
          <q-btn v-if="cart.length" class="full-width q-mt-xs" flat dense no-caps color="grey-6" icon="fa-solid fa-trash-can" label="Vaciar pedido" @click="clearCart"/>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Vista ampliada del producto -->
    <q-dialog v-model="previewDialog">
      <q-card class="preview" style="width:390px;max-width:92vw">
        <div class="preview__wrap">
          <img v-if="previewed?.foto" :src="photo(previewed.foto)" :alt="previewed?.nombre"/>
          <q-icon v-else name="fa-solid fa-box" size="60px" color="grey-8"/>
          <q-btn flat round dense icon="fa-solid fa-xmark" class="preview__fa-solid fa-xmark" v-close-popup/>
        </div>
        <q-card-section>
          <div class="card__cat"><q-icon :name="categoryIcon(previewed?.categoria)" size="11px"/>{{previewed?.categoria}}</div>
          <div class="text-subtitle1 text-weight-bold text-white">{{previewed?.nombre}}</div>
          <div class="text-h6 text-amber-6">Bs {{money(previewed?.precio_venta)}}<span class="card__unit-inline">/{{previewed?.unidad}}</span></div>
          <div class="text-caption text-grey-5"><q-icon :name="previewed?.disponible?'fa-solid fa-circle-check':'fa-solid fa-ban'" size="13px"/> {{previewed?.disponible?'Disponible en tienda':'Momentáneamente sin stock'}}</div>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat no-caps color="grey-5" label="Cerrar" v-close-popup/>
          <q-btn unelevated no-caps class="btn-add btn-add--lg" :disable="!previewed?.disponible" @click="add(previewed);previewDialog=false"><q-icon name="fa-solid fa-cart-plus" size="16px" class="q-mr-xs"/>Agregar al pedido</q-btn>
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
const {proxy}=getCurrentInstance()
const CART_KEY='carritoTienda'
const tienda=reactive({empresa:{nombre:'',direccion:'',telefono:'',logo:null},whatsapp:'59165417608',categorias:[],total_productos:0})
const products=ref([]),cart=ref([]),search=ref(''),category=ref(null),order=ref('nombre'),onlyAvailable=ref(false)
const page=ref(1),lastPage=ref(1),loading=ref(false),cartDialog=ref(false),previewDialog=ref(false),previewed=ref(null)
const client=reactive({nombre:'',telefono:'',entrega:'RECOJO',direccion:'',nota:''})
// El botón de volver al menú es sólo para quien entró desde el sistema; el cliente no lo ve.
const isStaff=!!localStorage.getItem('tokenBean')
const orderOptions=[{label:'Nombre',value:'nombre'},{label:'Precio: menor a mayor',value:'precio_asc'},{label:'Precio: mayor a menor',value:'precio_desc'}]
// Cada categoría lleva su icono; lo que no coincide cae en una etiqueta genérica.
const CATEGORY_ICONS=[[/pollo|ave|huevo/,'fa-solid fa-egg'],[/cerdo|chancho/,'fa-solid fa-bacon'],[/res|carne|churrasco|parrilla/,'fa-solid fa-drumstick-bite'],[/embutido|salchi|chorizo|jamon/,'fa-solid fa-drumstick-bite'],
  [/queso|lacteo|leche|yogur|mantequilla/,'fa-solid fa-cheese'],[/alcohol|cerveza|vino|singani|ron/,'fa-solid fa-wine-bottle'],[/bebida|gaseosa|agua|jugo|refresco/,'fa-solid fa-bottle-water'],
  [/congelad|helado|hielo/,'fa-solid fa-snowflake'],[/limpieza|higiene|deterg|jabon/,'fa-solid fa-spray-can-sparkles'],[/pan|galleta|torta|reposteria/,'fa-solid fa-bread-slice'],
  [/fruta|verdura|hortaliza/,'fa-solid fa-apple-whole'],[/chocolate|dulce|caramelo|golosina/,'fa-solid fa-cookie-bite'],[/snack|papa|extrusado/,'fa-solid fa-burger'],
  [/condimento|adherezo|aderezo|sazon|especia/,'fa-solid fa-utensils'],[/bebe|pañal|higiene personal/,'fa-solid fa-baby'],[/despensa|abarrote|aceite|fideo|arroz|azucar/,'fa-solid fa-basket-shopping']]
const categoryIcon=name=>{const t=String(name||'').toLowerCase();return (CATEGORY_ICONS.find(([re])=>re.test(t))||[null,'fa-solid fa-tag'])[1]}
let searchTimer=null
const money=v=>Number(v||0).toFixed(2)
const photo=path=>`${proxy.$imgBase}/images/${path}`
const logoUrl=computed(()=>tienda.empresa.logo?photo(tienda.empresa.logo):null)
const cartCount=computed(()=>cart.value.length)
const cartTotal=computed(()=>cart.value.reduce((s,i)=>s+Number(i.cantidad||0)*Number(i.precio_venta||0),0))
const prettyPhone=computed(()=>`+${tienda.whatsapp.replace(/^(\d{3})(\d+)$/,'$1 $2')}`)
const cartItem=id=>cart.value.find(i=>i.id===id)
const inCart=id=>!!cartItem(id)

async function loadTienda(){
  try{
    const {data}=await proxy.$axios.get('/tienda')
    Object.assign(tienda,data)
    document.title=`${tienda.empresa.nombre||'Tienda'} · Pedidos en línea`
  }catch{/* la vitrina sigue funcionando con los productos */}
}
async function loadProducts(append=false){
  loading.value=true
  try{
    const {data}=await proxy.$axios.get('/tienda/productos',{params:{q:search.value||'',categoria_id:category.value||'',orden:order.value,solo_disponibles:onlyAvailable.value?1:0,page:page.value,per_page:24}})
    products.value=append?[...products.value,...data.data]:data.data
    lastPage.value=data.last_page||1
  }catch{proxy.$alert.error('No se pudieron cargar los productos')}
  finally{loading.value=false}
}
function reload(){page.value=1;loadProducts()}
function loadMore(){page.value++;loadProducts(true)}
function onSearch(){clearTimeout(searchTimer);searchTimer=setTimeout(reload,300)}
function selectCategory(id){category.value=category.value===id?null:id;reload()}
function clearFilters(){search.value='';category.value=null;onlyAvailable.value=false;reload()}
function preview(p){previewed.value=p;previewDialog.value=true}

// Carrito: vive en localStorage para que el cliente no lo pierda al recargar.
function add(p){
  if(!p||!p.disponible)return
  cart.value.push({id:p.id,codigo:p.codigo,nombre:p.nombre,unidad:p.unidad,precio_venta:Number(p.precio_venta),foto:p.foto,cantidad:1})
  proxy.$alert.success(`${p.nombre} agregado al pedido`)
}
function step(id,delta){
  const item=cartItem(id);if(!item)return
  const paso=item.unidad==='KG'?0.5:1,valor=Number((Number(item.cantidad||0)+delta*paso).toFixed(3))
  if(valor<=0)return remove(id)
  item.cantidad=valor
}
function normalize(id){
  const item=cartItem(id);if(!item)return
  const valor=Number(item.cantidad)
  if(!valor||valor<=0)return remove(id)
  item.cantidad=item.unidad==='KG'?Number(valor.toFixed(3)):Math.max(1,Math.round(valor))
}
function remove(id){cart.value=cart.value.filter(i=>i.id!==id)}
function clearCart(){proxy.$alert.dialog('¿Vaciar el pedido?').onOk(()=>{cart.value=[]})}

/** Chat directo con la tienda, sin pedido armado (botón flotante y pie). */
function chatWhatsapp(){
  const saludo=`Hola ${tienda.empresa.nombre||''}, quiero hacer un pedido`.replace(/\s+/g,' ')
  window.open(`https://wa.me/${tienda.whatsapp}?text=${encodeURIComponent(saludo)}`,'_blank')
}
/** Arma el texto del pedido y abre WhatsApp con el número de la tienda. */
function sendWhatsapp(){
  if(!cart.value.length)return
  if(!client.nombre.trim())return proxy.$alert.warning('Escribe tu nombre para que sepamos quién pide')
  if(client.entrega==='DELIVERY'&&!client.direccion.trim())return proxy.$alert.warning('Escribe la dirección de entrega')
  const cantidad=i=>Number(i.cantidad).toFixed(i.unidad==='KG'?3:0).replace(/\.?0+$/,m=>m.includes('.')?'':m)
  const lineas=cart.value.map((i,n)=>`${n+1}. ${i.nombre} — ${cantidad(i)} ${i.unidad} x Bs ${money(i.precio_venta)} = Bs ${money(i.cantidad*i.precio_venta)}`)
  const texto=[
    `*NUEVO PEDIDO — ${tienda.empresa.nombre||'Tienda'}*`,
    '',
    ...lineas,
    '',
    `*Total referencial: Bs ${money(cartTotal.value)}*`,
    '',
    `Cliente: ${client.nombre.trim()}`,
    client.telefono?`Teléfono: ${client.telefono}`:null,
    client.entrega==='DELIVERY'?`Entrega: Delivery — ${client.direccion.trim()}`:'Entrega: Recojo en tienda',
    client.nota?`Nota: ${client.nota.trim()}`:null,
    '',
    'Enviado desde la tienda en línea'
  ].filter(Boolean).join('\n')
  window.open(`https://wa.me/${tienda.whatsapp}?text=${encodeURIComponent(texto)}`,'_blank')
}
/** Compartir la tienda: menú nativo del celular y, si no hay, copia el enlace. */
async function share(){
  const datos={title:tienda.empresa.nombre||'Tienda',text:'Mirá el catálogo y pedí por WhatsApp',url:location.href}
  try{
    if(navigator.share)await navigator.share(datos)
    else{await navigator.clipboard.writeText(location.href);proxy.$alert.success('Enlace copiado')}
  }catch{/* el usuario canceló */}
}

watch(cart,value=>localStorage.setItem(CART_KEY,JSON.stringify(value)),{deep:true})
onMounted(()=>{
  try{cart.value=JSON.parse(localStorage.getItem(CART_KEY)||'[]')}catch{cart.value=[]}
  loadTienda();loadProducts()
})
onBeforeUnmount(()=>clearTimeout(searchTimer))
</script>

<style scoped>
/* Carta oscura con acento ámbar: la vitrina se lee como un menú impreso. */
.shop{background:#0f0f10;color:#e8e6e3;padding-bottom:78px;min-height:100vh}
.bar{position:sticky;top:0;z-index:20;background:#141416;border-bottom:1px solid #26262a}
.bar__inner{max-width:1220px;margin:0 auto;display:flex;align-items:center;gap:8px;padding:7px 12px}
.brand{display:flex;align-items:center;gap:8px;min-width:0}
.brand__logo{background:#1e1e22;border:1px solid #2e2e34;border-radius:9px}
.brand__logo img{object-fit:contain}
.brand__name{font-weight:800;font-size:14px;line-height:16px;letter-spacing:.3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:190px}
.brand__sub{color:#ffb300;font-size:9.5px;line-height:12px;letter-spacing:.6px;text-transform:uppercase}
.bar__search{flex:1 1 200px;max-width:460px}
.bar__back{border-radius:8px}
.bar__cart{background:#ffb300;color:#141416;border-radius:9px;font-weight:800;padding:0 12px}
.hero{border-bottom:1px solid #26262a;background:radial-gradient(1100px 220px at 12% -30%,rgba(255,179,0,.16),transparent 60%),#131316}
.hero__inner{max-width:1220px;margin:0 auto;padding:18px 12px 16px;display:flex;align-items:center;gap:18px}
.hero__left{min-width:0;flex:1}
.hero__kicker{color:#ffb300;font-size:10px;font-weight:700;letter-spacing:2px;display:flex;align-items:center;gap:4px}
.hero__kicker .dot{width:4px;height:4px;border-radius:50%;background:#ffb300;display:inline-block}
.hero__title{margin:4px 0 0;font-size:30px;line-height:32px;font-weight:900;letter-spacing:-.4px;text-transform:uppercase}
.hero__text{margin:6px 0 9px;max-width:620px;font-size:12.5px;color:#a9a6a2}
.hero__chips{display:flex;flex-wrap:wrap;gap:5px}
.pill{display:inline-flex;align-items:center;gap:4px;background:#1c1c20;border:1px solid #2e2e34;color:#d6d3cf;font-size:10.5px;padding:3px 9px;border-radius:20px}
.pill .q-icon{color:#ffb300}
.hero__right{flex:0 0 auto}
.hero__ring{position:relative;width:118px;height:118px;border-radius:50%;border:2px solid rgba(255,179,0,.5);display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 8px rgba(255,179,0,.06)}
.hero__badge{position:absolute;bottom:-10px;right:-14px;background:#ffb300;color:#141416;border-radius:14px;padding:3px 10px;text-align:center;line-height:12px}
.hero__badge b{font-size:11px;display:block}
.hero__badge span{font-size:8px;letter-spacing:1px;font-weight:700}
.filters{max-width:1220px;margin:0 auto;padding:9px 12px 3px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.filters__chips{display:flex;gap:5px;overflow-x:auto;flex:1 1 320px;padding-bottom:3px;-webkit-mask-image:linear-gradient(90deg,#000 92%,transparent);mask-image:linear-gradient(90deg,#000 92%,transparent)}
.filters__chips::-webkit-scrollbar{height:4px}
.filters__chips::-webkit-scrollbar-thumb{background:#33333a;border-radius:3px}
.cat{display:inline-flex;align-items:center;gap:4px;white-space:nowrap;background:#1a1a1e;border:1px solid #2b2b31;color:#c9c6c2;font-size:11px;font-weight:600;padding:5px 11px;border-radius:20px;cursor:pointer;transition:.15s}
.cat:hover{border-color:#ffb300;color:#ffb300}
.cat--on{background:#ffb300;border-color:#ffb300;color:#141416}
.filters__right{display:flex;align-items:center;gap:8px}
.filters__toggle{font-size:11px}
.filters__order{min-width:186px}
.wrap{max-width:1220px;margin:0 auto;padding:6px 12px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(146px,1fr));gap:8px}
.card{background:#17171a;border:1px solid #26262a;border-radius:11px;overflow:hidden;display:flex;flex-direction:column;transition:.16s}
.card:hover{border-color:#3d3d45;transform:translateY(-2px);box-shadow:0 8px 20px rgba(0,0,0,.45)}
.card--off{opacity:.5}
.card--in{border-color:#ffb300}
.card__img{position:relative;height:112px;display:flex;align-items:center;justify-content:center;background:#fff;cursor:pointer}
.card__img img{width:100%;height:100%;object-fit:contain}
.card__price{position:absolute;right:5px;bottom:5px;background:#ffb300;color:#141416;font-weight:800;font-size:11.5px;padding:2px 8px;border-radius:12px;box-shadow:0 2px 6px rgba(0,0,0,.3)}
.card__flag{position:absolute;top:5px;left:5px;display:inline-flex;align-items:center;gap:3px;background:rgba(20,20,22,.86);color:#fff;font-size:9px;padding:2px 7px;border-radius:10px}
.card__flag--on{background:#2e7d32}
.card__body{padding:6px 7px 2px;flex:1}
.card__cat{display:flex;align-items:center;gap:3px;font-size:9px;color:#ffb300;text-transform:uppercase;letter-spacing:.5px}
.card__name{font-weight:600;font-size:11.5px;line-height:14px;height:28px;margin-top:2px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;color:#eceae7}
.card__unit{display:flex;align-items:center;gap:3px;font-size:9.5px;color:#8d8a86;margin-top:2px}
.card__unit-inline{font-size:12px;color:#8d8a86}
.card__foot{padding:5px 6px 6px}
.btn-add{width:100%;display:inline-flex;align-items:center;justify-content:center;gap:5px;background:#ffb300;color:#141416;border:0;border-radius:8px;padding:5px;font-size:11px;font-weight:800;cursor:pointer;transition:.15s}
.btn-add:hover:not(:disabled){background:#ffc233}
.btn-add:disabled{background:#2b2b31;color:#6d6a67;cursor:not-allowed}
.btn-add--lg{padding:7px 14px;font-size:12px}
.stepper{display:flex;align-items:center;justify-content:center;gap:1px;border:1px solid #4a3a12;background:#221c0c;border-radius:8px;padding:1px}
.stepper__input{width:48px;border:0;background:transparent;text-align:center;font-weight:800;font-size:12px;color:#ffb300;outline:none}
.stepper__unit{font-size:9px;color:#8d8a86}
.more{display:flex;justify-content:center;padding:16px 0 4px}
.empty{display:flex;flex-direction:column;align-items:center;gap:6px;padding:40px 12px;color:#8d8a86}
.foot{max-width:1220px;margin:16px auto 0;padding:16px 12px;border-top:1px solid #26262a;text-align:center}
.foot__brand{display:flex;align-items:center;justify-content:center;gap:6px;font-size:14px;letter-spacing:.4px}
.foot__data{display:flex;flex-wrap:wrap;justify-content:center;gap:12px;margin-top:6px;color:#8d8a86;font-size:11px}
.foot__data span{display:inline-flex;align-items:center;gap:4px}
.foot__data .q-icon{color:#ffb300}
.foot__social{display:flex;justify-content:center;gap:8px;margin-top:10px}
.social{background:#1c1c20;color:#ffb300;border:1px solid #2e2e34}
.social--wa{background:#25d366;color:#0b3d1f;border-color:#25d366}
.foot__legal{margin-top:10px;color:#6d6a67;font-size:10px}
.fabs{position:fixed;right:14px;bottom:14px;z-index:30;display:flex;flex-direction:column;gap:9px;align-items:center}
.fab{width:52px;height:52px;box-shadow:0 6px 18px rgba(0,0,0,.45)}
.fab--cart{background:#ffb300;color:#141416}
.fab__badge{background:#141416;color:#ffb300;font-weight:800}
.fab--wa{background:#25d366;color:#fff;animation:pulse 2.6s infinite}

@keyframes pulse{0%{box-shadow:0 0 0 0 rgba(37,211,102,.5)}70%{box-shadow:0 0 0 14px rgba(37,211,102,0)}100%{box-shadow:0 0 0 0 rgba(37,211,102,0)}}
.mobar{position:fixed;left:10px;right:84px;bottom:16px;z-index:29;display:flex;align-items:center;gap:8px;background:#ffb300;color:#141416;border-radius:12px;padding:7px 12px;box-shadow:0 6px 18px rgba(0,0,0,.45);cursor:pointer}
.mobar__count{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
.mobar__total{font-size:16px;font-weight:900;line-height:17px}
.mobar__cta{display:inline-flex;align-items:center;gap:4px;font-weight:800;font-size:12px}
.cart{width:430px;max-width:100vw;background:#141416;color:#e8e6e3}
.cart__head{background:#ffb300;color:#141416}
.cart__body{background:#141416}
.cart__section{display:flex;align-items:center;gap:5px;color:#ffb300;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;padding-top:4px}
.cart__thumb{background:#fff;border-radius:8px}
.cart__thumb img{object-fit:contain}
.cart__foot{background:#17171a;border-top:1px solid #26262a}
.btn-wa{background:#25d366;color:#0b3d1f;font-weight:800}
.preview{background:#17171a;color:#e8e6e3}
.preview__wrap{position:relative;background:#fff;height:250px;display:flex;align-items:center;justify-content:center}
.preview__wrap img{width:100%;height:100%;object-fit:contain}
.preview__close{position:absolute;top:6px;right:6px;background:rgba(20,20,22,.7);color:#fff}
@media(max-width:1023px){.hero__right{display:none}}
@media(max-width:599px){
  .bar__inner{flex-wrap:wrap}
  .bar__search{order:3;flex:1 1 100%;max-width:none}
  .hero__title{font-size:23px;line-height:26px}
  .grid{grid-template-columns:repeat(auto-fill,minmax(132px,1fr));gap:7px}
  .card__img{height:100px}
  .shop{padding-bottom:96px}
}
</style>
