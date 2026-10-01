<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm">
      <div><div class="text-subtitle1 text-weight-bold">Nueva venta offline</div><div class="text-caption text-grey-7">Cobra sin internet: la venta queda guardada en este dispositivo</div></div>
      <q-space/>
      <q-chip dense :color="offline?'negative':'positive'" text-color="white" :icon="offline?'wifi_off':'wifi'" :label="offline?'Sin conexión':'Con conexión'"/>
      <q-btn dense flat icon="cloud_queue" label="Ventas offline" no-caps to="/ventas/offline">
        <q-badge v-if="pendientes" color="negative" floating :label="pendientes"/>
      </q-btn>
    </div>

    <q-banner v-if="!puedeVender" dense rounded class="bg-orange-1 text-orange-9"><q-icon name="lock" class="q-mr-xs"/>No tienes el permiso <b>Crear Ventas Offline</b>. Pide a un administrador que te lo asigne.</q-banner>

    <template v-else>
      <q-banner v-if="!catalogo.productos.length" dense rounded class="bg-red-1 text-red-9 q-mb-sm">
        <q-icon name="cloud_download" class="q-mr-xs"/>Todavía no descargaste el catálogo. Con internet, toca <b>Actualizar catálogo</b> para poder vender sin conexión.
        <template #action><q-btn dense flat no-caps icon="download" label="Actualizar catálogo" :loading="descargando" @click="descargarCatalogo"/></template>
      </q-banner>
      <q-banner v-else dense rounded class="bg-blue-1 text-blue-9 q-mb-sm">
        <q-icon name="inventory_2" class="q-mr-xs"/>{{catalogo.productos.length}} productos guardados en este dispositivo · actualizado {{fechaCatalogo}}
        <template #action><q-btn dense flat no-caps icon="refresh" label="Actualizar catálogo" :loading="descargando" @click="descargarCatalogo"/></template>
      </q-banner>

      <div class="row q-col-gutter-sm">
        <div class="col-12 col-md-6">
          <q-card flat bordered>
            <q-card-section class="row q-col-gutter-sm q-pa-sm">
              <q-input ref="searchInput" v-model="search" dense outlined autofocus clearable debounce="200" class="col" placeholder="Buscar nombre, código o escanear etiqueta de balanza" @update:model-value="handleSearchInput" @keydown.enter.prevent="addExact">
                <template #prepend><q-icon name="qr_code_scanner"/></template>
              </q-input>
              <q-select v-model="category" :options="catalogo.categorias" option-label="nombre" option-value="id" emit-value map-options dense outlined clearable label="Categoría" style="min-width:170px"/>
            </q-card-section>
            <q-separator/>
            <q-card-section class="q-pa-sm product-grid">
              <q-card v-for="product in pageProducts" :key="product.id" flat bordered class="product-card cursor-pointer" :class="{'product-card--empty':disponible(product)<=0}" @click="add(product)">
                <div class="product-image"><img v-if="product.foto" :src="photoUrl(product.foto)"/><q-icon v-else name="inventory_2" size="42px" color="grey-4"/></div>
                <q-card-section class="q-pa-xs">
                  <div class="text-weight-bold product-name">{{product.nombre}}</div>
                  <div class="row items-center no-wrap product-meta"><span class="text-primary text-weight-bold">Bs {{money(product.precio_venta)}}{{product.unidad==='KG'?'/kg':''}}</span><q-space/><q-badge :color="disponible(product)>0?'positive':'negative'" :label="`${quantity(disponible(product),product.unidad)} ${product.unidad}`"/></div>
                </q-card-section>
                <q-tooltip :delay="300" anchor="top middle" self="bottom middle" class="bg-grey-10 product-tip"><div class="text-weight-bold">{{product.nombre}}</div><div>Código: {{product.codigo}}</div><div v-if="product.codigo_barras">Cód. barras: {{product.codigo_barras}}</div><div v-if="product.categoria">Categoría: {{product.categoria}}</div><div>Unidad: {{product.unidad}}</div><div>Stock: {{quantity(disponible(product),product.unidad)}} {{product.unidad}}</div><div>Precio: Bs {{money(product.precio_venta)}}{{product.unidad==='KG'?'/kg':''}}</div></q-tooltip>
              </q-card>
              <div v-if="!pageProducts.length" class="grid-empty text-center text-grey-6 q-py-lg">Sin productos</div>
            </q-card-section>
            <q-separator/>
            <q-card-section class="row items-center q-py-xs q-px-sm">
              <div class="text-caption text-grey-7">{{filteredProducts.length}} productos · página {{page}} de {{lastPage}}</div>
              <q-space/>
              <q-pagination v-model="page" :max="lastPage" :max-pages="5" boundary-numbers dense size="sm" color="primary"/>
            </q-card-section>
          </q-card>
        </div>

        <div class="col-12 col-md-6">
          <q-card flat bordered class="cart-card">
            <q-card-section class="row items-center q-py-sm"><q-icon name="shopping_cart" color="primary" size="22px" class="q-mr-xs"/><div class="text-subtitle1 text-weight-bold">Carrito</div><q-space/><q-badge color="primary" :label="cart.length"/><q-btn dense flat no-caps size="sm" class="q-ml-sm" icon="delete_sweep" color="negative" label="Limpiar" :disable="!cart.length" @click="clearCart"/></q-card-section>
            <q-separator/>
            <q-list v-if="cart.length" separator class="cart-list">
              <q-item v-for="item in cart" :key="item.id" dense class="q-px-sm">
                <q-item-section avatar class="cart-avatar"><q-avatar rounded size="28px" color="grey-2"><img v-if="item.foto" :src="photoUrl(item.foto)"/><q-icon v-else name="inventory_2" size="16px"/></q-avatar></q-item-section>
                <q-item-section>
                  <q-item-label lines="1" class="text-caption text-weight-medium">{{item.nombre}}</q-item-label>
                  <div class="row items-end no-wrap cart-fields">
                    <label class="field-label">Cant. ({{item.unidad}})<input v-model.number="item.cantidad" class="qty-input" type="number" :min="minimumQty(item)" :step="quantityStep(item)" @blur="validateQty(item)"></label>
                    <label class="field-label">Precio{{item.unidad==='KG'?'/kg':''}}<input v-model.number="item.precio_venta" class="price-input" type="number" min="0" step="0.0001" :readonly="!canEditPrice" @blur="syncLineTotal(item)"></label>
                    <label class="field-label total-label">Total<input v-model.number="item.total_editable" class="total-input" type="number" min="0" step="0.01" :readonly="!canEditPrice" @keyup.enter="$event.target.blur()" @blur="applyLineTotal(item)"></label>
                    <div class="row items-center no-wrap q-ml-auto"><q-btn dense flat round size="sm" icon="remove" @click="changeQty(item,-quantityStep(item))"/><q-btn dense flat round size="sm" icon="add" @click="changeQty(item,quantityStep(item))"/><q-btn dense flat round size="sm" icon="delete" color="negative" @click="removeItem(item)"/></div>
                  </div>
                </q-item-section>
              </q-item>
            </q-list>
            <q-card-section v-else class="text-center text-grey-6 q-py-xl"><q-icon name="remove_shopping_cart" size="42px"/><div>Agrega productos</div></q-card-section>
            <q-separator/>
            <q-card-section class="q-pa-sm">
              <q-select v-model="cashRegister" dense outlined emit-value map-options :options="cashRegisters" label="Caja" class="q-mb-sm"/>
              <q-input v-model.number="discount" dense outlined type="number" min="0" :max="subtotal" step="0.01" label="Descuento" prefix="Bs" class="q-mb-sm"/>
              <q-select v-model="paymentType" dense outlined :options="paymentTypes" label="Tipo de pago" class="q-mb-sm"/>
              <div v-if="paymentType==='COMBINADO'" class="row q-col-gutter-sm q-mb-sm">
                <q-input v-model.number="cashAmount" dense outlined type="number" min="0" step="0.01" label="Monto efectivo" prefix="Bs" class="col-6"/>
                <q-input v-model.number="qrAmount" dense outlined type="number" min="0" step="0.01" label="Monto QR" prefix="Bs" class="col-6"/>
                <div class="col-12 text-caption" :class="paymentDifference===0?'text-positive':'text-negative'">Diferencia: Bs {{money(paymentDifference)}}</div>
              </div>
              <q-banner v-else dense rounded :class="paymentType==='EFECTIVO'?'bg-green-1 text-green-9':'bg-blue-1 text-blue-9'" class="q-mb-sm"><q-icon :name="paymentType==='EFECTIVO'?'payments':'qr_code_2'" class="q-mr-xs"/>Pago {{paymentType}}: Bs {{money(total)}}</q-banner>
              <q-input v-model="observation" dense outlined autogrow label="Observación" class="q-mb-sm"/>
              <div class="row text-body2"><span>Subtotal</span><q-space/><b>Bs {{money(subtotal)}}</b></div>
              <div class="row text-body2 text-negative"><span>Descuento</span><q-space/><b>- Bs {{money(validDiscount)}}</b></div>
              <div class="row text-h6 text-primary q-mt-xs"><b>Total</b><q-space/><b>Bs {{money(total)}}</b></div>
            </q-card-section>
            <q-card-actions class="q-pa-sm"><q-btn class="full-width" color="primary" unelevated icon="save" label="Guardar venta offline" no-caps :disable="!cart.length||!catalogo.productos.length" @click="confirmSale"/></q-card-actions>
          </q-card>
        </div>
      </div>
    </template>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, onMounted, ref, watch } from 'vue'
import { printSale } from '../../addons/ventaPrint'
import { armarVentaOffline, catalogoOffline, guardarCatalogo, guardarVentaOffline, stockComprometido, ventasPorEnviar } from '../../addons/ventasOffline'
const {proxy}=getCurrentInstance()
const catalogo=ref(catalogoOffline()),descargando=ref(false),pendientes=ref(ventasPorEnviar().length)
const cart=ref([]),search=ref(''),category=ref(null),discount=ref(0),observation=ref(''),searchInput=ref(null)
const PER_PAGE=15,page=ref(1)
const paymentType=ref('EFECTIVO'),paymentTypes=['EFECTIVO','QR','COMBINADO'],cashAmount=ref(0),qrAmount=ref(0)
// La misma caja que eligió esta terminal en Nueva venta.
const cashRegisters=[1,2,3,4,5].map(n=>({label:`Caja ${n}`,value:n})),cashRegister=ref(Math.min(5,Math.max(1,Number(localStorage.getItem('cajaBean'))||1)))
watch(cashRegister,value=>localStorage.setItem('cajaBean',value))
// Sin este permiso el precio queda fijo al del catálogo (el servidor rechazaría otro al exportar).
const canEditPrice=computed(()=>proxy.$store.hasPermission('Modificar Precio en Venta'))
const puedeVender=computed(()=>proxy.$store.hasPermission(['Crear Ventas Offline','Crear Ventas']))
const offline=computed(()=>proxy.$store.offline||!navigator.onLine)
const photoUrl=path=>`${proxy.$imgBase}/images/${path}`,money=v=>Number(v||0).toFixed(2)
const isWeighted=item=>item?.unidad==='KG',quantityStep=item=>isWeighted(item)?0.001:1,minimumQty=item=>quantityStep(item)
const quantity=(value,unit)=>Number(value||0).toFixed(unit==='KG'?3:0)
const fechaCatalogo=computed(()=>catalogo.value.fecha?new Date(catalogo.value.fecha).toLocaleString('es-BO'):'nunca')

/* Stock disponible sin conexión: el del catálogo menos lo cobrado y todavía no exportado. */
const reservado=ref(stockComprometido())
function disponible(product){return Number((Number(product?.stock_inicial||0)-(reservado.value[product?.id]||0)).toFixed(3))}

/* Búsqueda y paginado en memoria: sin internet no hay a quién preguntarle. */
const filteredProducts=computed(()=>{const term=String(search.value||'').trim().toUpperCase();return catalogo.value.productos.filter(p=>{if(category.value&&Number(p.categoria_id)!==Number(category.value))return false;if(!term)return true;return String(p.nombre||'').toUpperCase().includes(term)||String(p.codigo||'').toUpperCase().includes(term)||String(p.codigo_barras||'').toUpperCase().includes(term)})})
const lastPage=computed(()=>Math.max(1,Math.ceil(filteredProducts.value.length/PER_PAGE)))
const pageProducts=computed(()=>filteredProducts.value.slice((page.value-1)*PER_PAGE,page.value*PER_PAGE))
watch([search,category],()=>{page.value=1})
watch(lastPage,max=>{if(page.value>max)page.value=max})

const subtotal=computed(()=>cart.value.reduce((sum,i)=>sum+Number(i.precio_venta)*i.cantidad,0))
const validDiscount=computed(()=>Math.min(Math.max(Number(discount.value)||0,0),subtotal.value))
const total=computed(()=>subtotal.value-validDiscount.value)
const paymentDifference=computed(()=>Number((total.value-(Number(cashAmount.value)||0)-(Number(qrAmount.value)||0)).toFixed(2)))
watch([paymentType,total],()=>{if(paymentType.value==='EFECTIVO'){cashAmount.value=total.value;qrAmount.value=0}else if(paymentType.value==='QR'){cashAmount.value=0;qrAmount.value=total.value}else if(Number(cashAmount.value)+Number(qrAmount.value)===0){cashAmount.value=total.value;qrAmount.value=0}})

/* --------------------------- copia local del catálogo -------------------------- */
async function descargarCatalogo(){descargando.value=true;try{const productos=[];let pagina=1,ultima=1;do{const {data}=await proxy.$axios.get('/productos',{params:{per_page:500,page:pagina}});productos.push(...data.data);ultima=data.last_page||1;pagina++}while(pagina<=ultima);const {data}=await proxy.$axios.get('/productos-catalogos');const guardados=guardarCatalogo(productos,data.categorias);if(!guardados)return proxy.$alert.error('No se pudo guardar el catálogo: el almacenamiento del dispositivo está lleno');catalogo.value=catalogoOffline();proxy.$alert.success('Catálogo actualizado',`${guardados} productos disponibles sin conexión`)}catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo descargar el catálogo. Conéctate a internet e intenta de nuevo')}finally{descargando.value=false}}

/* --------------------------------- carrito -------------------------------- */
function add(product,amount=null){if(!product?.id)return proxy.$alert.error('El producto no existe');const requested=Number(amount??quantityStep(product));const item=cart.value.find(i=>i.id===product.id);const next=Number(((item?.cantidad||0)+requested).toFixed(3));if(next>disponible(product)+.0001)return proxy.$alert.error(`Stock insuficiente: disponible ${quantity(disponible(product),product.unidad)} ${product.unidad}`);if(item){item.cantidad=next;syncLineTotal(item)}else cart.value.push({...product,cantidad:requested,total_editable:(Number(product.precio_venta)*requested).toFixed(2)});proxy.$alert.success(`${product.nombre} se agregó al carrito`,`${quantity(next,product.unidad)} ${product.unidad} · Bs ${money(Number(product.precio_venta)*next)}`)}
function parseScaleBarcode(value){const code=String(value||'').trim();if(!/^2\d{12}$/.test(code))return null;const expected=ean13CheckDigit(code.slice(0,12));if(expected!==Number(code[12]))return null;return{productCode:code.slice(0,7),weight:Number(code.slice(7,12))/1000}}
function ean13CheckDigit(firstTwelve){const sum=[...firstTwelve].reduce((total,digit,index)=>total+Number(digit)*(index%2===0?1:3),0);return(10-(sum%10))%10}
function handleSearchInput(value){if(parseScaleBarcode(value))return addExact();const code=String(value||'').trim().toUpperCase();if(!code)return;const product=catalogo.value.productos.find(p=>String(p.codigo_barras||'').trim().toUpperCase()===code);if(product){add(product,isWeighted(product)?null:1);search.value='';searchInput.value?.focus()}}
function addExact(){const q=(search.value||'').trim().toUpperCase();const scale=parseScaleBarcode(q);if(scale){const product=catalogo.value.productos.find(p=>String(p.codigo)===scale.productCode||String(p.codigo_barras)===scale.productCode);if(!product)return proxy.$alert.error(`No existe un producto con código de balanza ${scale.productCode}`);if(product.unidad!=='KG')return proxy.$alert.error(`${product.nombre} debe tener unidad KG`);add(product,scale.weight);search.value='';return}if(!q)return;const product=catalogo.value.productos.find(p=>String(p.codigo||'').toUpperCase()===q||String(p.codigo_barras||'').toUpperCase()===q);if(!product)return proxy.$alert.error(`No existe el producto ${q}`);add(product,isWeighted(product)?null:1);search.value=''}
function changeQty(item,amount){const next=Number((Number(item.cantidad)+amount).toFixed(3));if(next<minimumQty(item))return removeItem(item);if(next>disponible(item)+.0001)return proxy.$alert.error('Stock insuficiente');item.cantidad=next;syncLineTotal(item)}
function validateQty(item){let value=Number(item.cantidad)||minimumQty(item);value=isWeighted(item)?Math.round(value*1000)/1000:Math.floor(value);if(value>disponible(item)){item.cantidad=disponible(item);proxy.$alert.error('La cantidad fue ajustada al stock disponible')}else item.cantidad=Math.max(minimumQty(item),value);syncLineTotal(item)}
function syncLineTotal(item){item.total_editable=(Number(item.precio_venta||0)*Number(item.cantidad||0)).toFixed(2)}
function applyLineTotal(item){if(!canEditPrice.value)return syncLineTotal(item);const totalValue=Math.max(0,Number(item.total_editable)||0),lineQuantity=Math.max(minimumQty(item),Number(item.cantidad)||minimumQty(item));item.total_editable=totalValue.toFixed(2);item.precio_venta=Number((totalValue/lineQuantity).toFixed(4))}
function removeItem(item){cart.value=cart.value.filter(i=>i.id!==item.id)}
function clearCart(){if(!cart.value.length)return;proxy.$alert.confirm('¿Vaciar todo el carrito?').onOk(()=>{cart.value=[];discount.value=0;observation.value='';proxy.$alert.info('Carrito vacío');searchInput.value?.focus()})}

/* --------------------------- guardar venta offline -------------------------- */
function confirmSale(){cart.value.forEach(item=>{const requestedTotal=item.total_editable;validateQty(item);item.total_editable=requestedTotal;applyLineTotal(item)});if(cart.value.some(i=>Number(i.precio_venta)<0||i.precio_venta===''))return proxy.$alert.error('Revisa los precios de venta');if(paymentType.value==='COMBINADO'&&paymentDifference.value!==0)return proxy.$alert.error('Efectivo y QR deben sumar el total');proxy.$alert.dialog(`¿Guardar la venta offline por Bs ${money(total.value)}?`).onOk(()=>{const venta=armarVentaOffline({caja:cashRegister.value,items:cart.value,descuento:validDiscount.value,tipoPago:paymentType.value,efectivo:cashAmount.value,qr:qrAmount.value,observacion:observation.value,usuario:proxy.$store.user.name||proxy.$store.user.username||''})
  try{guardarVentaOffline(venta)}catch(e){return proxy.$alert.error(e.message)}
  reservado.value=stockComprometido();pendientes.value=ventasPorEnviar().length
  proxy.$alert.success(`Venta ${venta.numero_local} guardada en este dispositivo`,'Expórtala a ventas desde "Ventas offline" cuando tengas internet')
  printSale({...venta,numero:venta.numero_local,estado:'PENDIENTE',pendiente:true})
  cart.value=[];discount.value=0;observation.value='';paymentType.value='EFECTIVO';searchInput.value?.focus()})}

onMounted(()=>{
  // Con internet se refresca el catálogo solo si nunca se bajó o si ya pasaron 12 h.
  const vencido=!catalogo.value.fecha||Date.now()-catalogo.value.fecha>12*60*60*1000
  if(puedeVender.value&&vencido&&navigator.onLine)descargarCatalogo()
})
</script>

<style scoped>
.product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(105px,1fr));gap:5px;max-height:calc(100vh - 260px);overflow:auto}.grid-empty{grid-column:1/-1}.product-card{transition:.15s;overflow:hidden}.product-card:hover{border-color:#f57c00;transform:translateY(-1px)}.product-card--empty{opacity:.55}.product-image{height:48px;background:#fffaf3;display:flex;align-items:center;justify-content:center}.product-image img{width:100%;height:100%;object-fit:contain}.product-name{font-size:10px;line-height:12px;height:36px;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;word-break:break-word}.product-meta{font-size:10px}.product-tip{font-size:11px;line-height:15px}.product-meta .q-badge{font-size:10px;padding:1px 4px}.cart-card{position:sticky;top:62px}.cart-list{max-height:38vh;overflow:auto}.cart-avatar{min-width:28px;padding-right:6px}.cart-fields{gap:6px;margin-top:2px}.field-label{font-size:9px;color:#607d8b;display:flex;flex-direction:column;line-height:11px}.price-input,.qty-input,.total-input{width:66px;height:22px;border:1px solid #cfd8dc;border-radius:4px;padding:1px 4px;font-size:12px;color:#263238;background:#fff}.qty-input{width:58px}.total-input{width:70px;font-weight:700;color:#e65100}.price-input:focus,.qty-input:focus,.total-input:focus{outline:1px solid #f57c00;border-color:#f57c00}@media(max-width:1023px){.cart-card{position:static}.product-grid{max-height:none}}
</style>
