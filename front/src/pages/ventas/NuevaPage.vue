<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm">
      <div><div class="text-subtitle1 text-weight-bold">Nueva venta</div><div class="text-caption text-grey-7">Busca el producto, indica la cantidad y confirma el cobro</div></div>
      <q-space/><q-btn dense flat icon="receipt_long" label="Ver ventas" no-caps to="/ventas"/>
    </div>
    <div class="row q-col-gutter-sm">
      <div class="col-12 col-md-7">
        <q-card flat bordered>
          <q-card-section class="row q-col-gutter-sm q-pa-sm">
            <q-input ref="searchInput" v-model="search" dense outlined autofocus clearable class="col" placeholder="Buscar nombre, código o escanear etiqueta de balanza" @update:model-value="handleSearchInput" @keydown.enter.prevent="addExact(true,$event.target.value)" @keydown.down.prevent="focusGrid">
              <template #prepend><q-icon name="qr_code_scanner"/></template>
              <template #hint><span class="text-grey-6">↓ entra a la lista · ← → ↑ ↓ navega · Enter agrega · Esc vuelve al buscador</span></template>
            </q-input>
            <q-select v-model="category" :options="categories" option-label="nombre" dense outlined clearable label="Categoría" style="min-width:170px" @update:model-value="resetProductsPage"/>
          </q-card-section>
          <q-separator/>
          <q-card-section v-if="loadingProducts" class="flex flex-center product-loading"><q-spinner color="primary" size="38px"/></q-card-section>
          <q-card-section v-else-if="!products.length" class="text-center text-grey-6 q-py-xl"><q-icon name="search_off" size="42px"/><div>Sin productos para esta búsqueda</div></q-card-section>
          <div v-else ref="gridRef" tabindex="0" class="q-pa-sm product-grid" @keydown="handleGridKeyboard" @blur="highlighted=-1">
            <q-card v-for="(product,index) in products" :key="product.id" flat bordered class="product-card cursor-pointer" :class="{'product-card--empty':product.stock_inicial<=0,'product-card--active':index===highlighted}" @click="openProductDialog(product)">
              <div class="product-image"><img v-if="product.foto" :src="photoUrl(product.foto)"/><q-icon v-else name="inventory_2" size="42px" color="grey-4"/></div>
              <q-card-section class="q-pa-xs">
                <div class="text-caption text-weight-bold ellipsis-2-lines product-name">{{product.nombre}}</div>
                <div class="row items-center"><span class="text-primary text-weight-bold">Bs {{money(product.precio_venta)}}{{product.unidad==='KG'?'/kg':''}}</span><q-space/><q-badge :color="product.stock_inicial>0?'positive':'negative'" :label="`Stock ${quantity(product.stock_inicial,product.unidad)} ${product.unidad}`"/></div>
              </q-card-section>
            </q-card>
          </div>
          <q-separator/>
          <q-card-actions class="row items-center justify-between q-px-sm">
            <span class="text-caption text-grey-7">{{productsFrom}}–{{productsTo}} de {{productsTotal}} productos</span>
            <q-pagination v-model="productsPage" :max="productsLastPage" :max-pages="6" boundary-numbers direction-links color="primary" size="sm" @update:model-value="loadProducts"/>
          </q-card-actions>
        </q-card>
      </div>

      <div class="col-12 col-md-5">
        <q-card flat bordered class="cart-card">
          <q-card-section class="row items-center q-py-sm"><q-icon name="shopping_cart" color="primary" size="22px" class="q-mr-xs"/><div class="text-subtitle1 text-weight-bold">Carrito</div><q-space/><q-btn v-if="cart.length" dense flat color="negative" icon="delete_sweep" label="Vaciar" no-caps class="q-mr-xs" @click="clearCart"/><q-badge color="primary" :label="itemCount"/></q-card-section>
          <q-separator/>
          <q-list v-if="cart.length" separator class="cart-list">
            <q-item v-for="item in cart" :key="item.id" dense class="q-px-xs cart-item">
              <q-item-section avatar class="cart-thumb"><q-avatar rounded size="28px" color="grey-2"><img v-if="item.foto" :src="photoUrl(item.foto)"/><q-icon v-else name="inventory_2" size="18px"/></q-avatar></q-item-section>
              <q-item-section class="cart-item-content">
                <q-item-label lines="1" class="text-caption text-weight-medium cart-item-name">{{item.nombre}}</q-item-label>
                <div class="cart-fields-row">
                  <label class="price-label"><span>{{item.unidad==='KG'?'Kilos':'Cantidad'}}</span><input v-model.number="item.cantidad" class="qty-input" type="number" :min="minimumQty(item)" :max="item.stock_inicial" :step="quantityStep(item)" @blur="validateQty(item)"></label>
                  <label class="price-label"><span>{{item.unidad==='KG'?'Precio Bs/kg':'Precio Bs'}}</span><input v-model.number="item.precio_venta" class="price-input" :class="{'input-locked':!canEditPrice}" :readonly="!canEditPrice" :title="canEditPrice?'':'Requiere el permiso «Modificar Precio en Venta»'" type="number" min="0" step="0.0001" @blur="syncLineTotal(item)"></label>
                  <label class="total-label"><span>Total</span><input v-model.number="item.total_editable" class="total-input" :class="{'input-locked':!canEditPrice}" :readonly="!canEditPrice" :title="canEditPrice?'':'Requiere el permiso «Modificar Precio en Venta»'" type="number" min="0" step="0.01" @keyup.enter="$event.target.blur()" @blur="applyLineTotal(item)"></label>
                  <div class="cart-actions"><q-btn dense flat round size="xs" icon="remove" @click="changeQty(item,-quantityStep(item))"/><q-btn dense flat round size="xs" icon="add" @click="changeQty(item,quantityStep(item))"/><q-btn dense flat round size="xs" icon="delete" color="negative" @click="removeItem(item)"/></div>
                </div>
              </q-item-section>
            </q-item>
          </q-list>
          <q-card-section v-else class="text-center text-grey-6 q-py-xl"><q-icon name="remove_shopping_cart" size="42px"/><div>Agrega productos</div></q-card-section>
          <q-separator/>
          <q-card-section class="q-pa-md cart-summary">
            <div class="row text-body2"><span>Subtotal</span><q-space/><b>Bs {{money(subtotal)}}</b></div>
            <div class="row text-body2 text-negative"><span>Descuento</span><q-space/><b>- Bs {{money(validDiscount)}}</b></div>
            <div class="row text-h6 text-primary q-mt-xs"><b>Total</b><q-space/><b>Bs {{money(total)}}</b></div>
          </q-card-section>
          <q-card-actions class="q-pa-sm"><q-btn class="full-width checkout-btn" color="positive" unelevated icon="point_of_sale" label="Continuar y cobrar · Enter" no-caps :disable="!cart.length" @click="openCheckout"/></q-card-actions>
        </q-card>
      </div>
    </div>

    <q-dialog v-model="productDialog" @hide="focusSearch">
      <q-card style="width:420px;max-width:94vw">
        <q-form @submit.prevent="confirmProduct">
          <q-card-section class="row items-center q-py-sm bg-primary text-white"><q-avatar rounded color="white" text-color="primary" size="38px"><img v-if="selectedProduct?.foto" :src="photoUrl(selectedProduct.foto)"/><q-icon v-else name="inventory_2"/></q-avatar><div class="q-ml-sm col"><div class="text-subtitle1 text-weight-bold ellipsis">{{selectedProduct?.nombre}}</div><div class="text-caption">Disponible {{quantity(availableStock,selectedProduct?.unidad)}} {{selectedProduct?.unidad}}</div></div><q-btn flat round dense icon="close" color="white" v-close-popup/></q-card-section>
          <q-card-section class="row q-col-gutter-sm q-pa-md">
            <q-input ref="quickQtyInput" v-model.number="quickQuantity" autofocus outlined dense type="number" :min="minimumQty(selectedProduct)" :max="availableStock" :step="quantityStep(selectedProduct)" :label="selectedProduct?.unidad==='KG'?'Kilos':'Cantidad'" class="col-12 col-sm-6" input-class="text-h6 text-weight-bold" @focus="$event.target.select()"><template #prepend><q-icon name="scale"/></template></q-input>
            <q-input v-model.number="quickPrice" outlined dense type="number" min="0" step="0.0001" :readonly="!canEditPrice" :bg-color="canEditPrice?undefined:'grey-3'" :label="selectedProduct?.unidad==='KG'?'Precio Bs/kg':'Precio Bs'" class="col-12 col-sm-6" input-class="text-h6 text-weight-bold" @focus="$event.target.select()"><template #prepend><q-icon name="payments"/></template><template v-if="!canEditPrice" #append><q-icon name="lock" size="18px" color="grey-6"><q-tooltip>Requiere el permiso «Modificar Precio en Venta»</q-tooltip></q-icon></template></q-input>
            <div class="col-12 row items-center q-pa-sm rounded-borders bg-orange-1 text-orange-10"><span>Total</span><q-space/><b class="text-h6">Bs {{money(Number(quickQuantity)*Number(quickPrice))}}</b></div>
          </q-card-section>
          <q-separator/><q-card-actions align="right" class="q-pa-sm"><q-btn flat dense label="Cancelar" no-caps v-close-popup/><q-btn type="submit" dense unelevated color="positive" icon="add_shopping_cart" label="Agregar · Enter" no-caps/></q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <q-dialog v-model="checkoutDialog" :maximized="$q.screen.lt.sm" @hide="focusSearch">
      <q-card class="column no-wrap" style="width:680px;max-width:94vw;max-height:92vh">
        <q-card-section class="row items-center q-pa-sm bg-primary text-white"><q-avatar color="white" text-color="primary" icon="receipt_long" size="32px"/><div class="q-ml-sm"><div class="text-subtitle1 text-weight-bold">Confirmar venta</div><div class="text-caption">Revisa el detalle y la forma de pago</div></div><q-space/><q-btn flat round dense icon="close" color="white" v-close-popup/></q-card-section>
        <q-card-section class="col scroll q-pa-sm">
          <div class="section-title"><q-icon name="shopping_bag"/> Resumen de productos</div>
          <q-markup-table flat bordered dense class="summary-table"><thead><tr><th class="text-left">Producto</th><th class="text-right">Cant.</th><th class="text-right">Precio</th><th class="text-right">Total</th></tr></thead><tbody><tr v-for="item in cart" :key="item.id"><td>{{item.nombre}}</td><td class="text-right">{{quantity(item.cantidad,item.unidad)}} {{item.unidad}}</td><td class="text-right">Bs {{money(item.precio_venta)}}</td><td class="text-right text-weight-bold">Bs {{money(Number(item.precio_venta)*item.cantidad)}}</td></tr></tbody></q-markup-table>
          <div class="section-title q-mt-sm"><q-icon name="point_of_sale"/> Caja</div>
          <q-btn-toggle v-model="cashRegister" :options="cashRegisters" no-caps unelevated spread toggle-color="primary" color="grey-3" text-color="grey-9" class="caja-toggle"/>
          <div class="section-title q-mt-sm"><q-icon name="payments"/> Pago</div>
          <div class="row q-col-gutter-sm"><q-input v-model.number="discount" outlined dense type="number" min="0" :max="subtotal" step="0.01" label="Descuento" prefix="Bs" class="col-12 col-sm-4"/><q-select v-model="paymentType" outlined dense :options="paymentTypes" label="Forma de pago" class="col-12 col-sm-4"/><q-input v-if="paymentType!=='COMBINADO'" :model-value="money(total)" outlined dense readonly label="Monto a cobrar" prefix="Bs" class="col-12 col-sm-4"/><q-input v-if="paymentType==='COMBINADO'" v-model.number="cashAmount" outlined dense type="number" min="0" step="0.01" label="Efectivo" prefix="Bs" class="col-6 col-sm-4"/><q-input v-if="paymentType==='COMBINADO'" v-model.number="qrAmount" outlined dense type="number" min="0" step="0.01" label="QR" prefix="Bs" class="col-6 col-sm-4"/><q-input v-model="observation" outlined dense autogrow label="Observación" class="col-12"/></div>
          <div v-if="paymentType==='COMBINADO'" class="text-caption q-mt-xs" :class="paymentDifference===0?'text-positive':'text-negative'">Diferencia por distribuir: Bs {{money(paymentDifference)}}</div>
          <div class="row items-center q-mt-sm q-pa-sm rounded-borders bg-blue-grey-9 text-white"><div><div class="text-caption">TOTAL A COBRAR</div><div class="text-h5 text-weight-bold">Bs {{money(total)}}</div></div><q-space/><div class="text-right text-caption">{{itemCount}} productos · Descuento Bs {{money(validDiscount)}}</div></div>
        </q-card-section>
        <q-separator/><q-card-actions align="right" class="q-pa-sm bg-white"><q-btn flat dense label="Volver" no-caps v-close-popup/><q-btn color="positive" dense unelevated icon="save" label="Guardar venta · Enter" no-caps :loading="saving" @click="confirmSale"/></q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { printSale } from '../../addons/ventaPrint'
const {proxy}=getCurrentInstance()
const products=ref([]),categories=ref([]),cart=ref([]),search=ref(''),category=ref(null),discount=ref(0),observation=ref(''),saving=ref(false),searchInput=ref(null)
const productDialog=ref(false),selectedProduct=ref(null),quickQuantity=ref(1),quickPrice=ref(0),quickQtyInput=ref(null)
const productsPage=ref(1),productsLastPage=ref(1),productsTotal=ref(0),productsFrom=ref(0),productsTo=ref(0),loadingProducts=ref(false)
const productsPerPage=20
const paymentType=ref('EFECTIVO'),paymentTypes=['EFECTIVO','QR','COMBINADO'],cashAmount=ref(0),qrAmount=ref(0)
// La caja elegida se recuerda en el navegador: cada terminal cobra siempre por la suya.
const cashRegisters=[1,2,3,4,5].map(n=>({label:`Caja ${n}`,value:n}))
const cashRegister=ref(Math.min(5,Math.max(1,Number(localStorage.getItem('cajaBean'))||1)))
watch(cashRegister,value=>localStorage.setItem('cajaBean',value))
const checkoutDialog=ref(false),gridRef=ref(null),highlighted=ref(-1)
let processingBarcode=false,productsSearchTimer=null,checkoutOpenedAt=0
const photoUrl=path=>`${proxy.$imgBase}/images/${path}`,money=v=>Number(v||0).toFixed(2)
// Sin este permiso el precio de la línea queda fijo al del catálogo (el backend responde 403 si igual se manda otro).
const canEditPrice=computed(()=>proxy.$store.hasPermission('Modificar Precio en Venta'))
const isWeighted=item=>item?.unidad==='KG',quantityStep=item=>isWeighted(item)?0.001:1,minimumQty=item=>quantityStep(item)
const quantity=(value,unit)=>Number(value||0).toFixed(unit==='KG'?3:0)
const subtotal=computed(()=>cart.value.reduce((sum,i)=>sum+Number(i.precio_venta)*i.cantidad,0))
const validDiscount=computed(()=>Math.min(Math.max(Number(discount.value)||0,0),subtotal.value))
const total=computed(()=>subtotal.value-validDiscount.value)
const itemCount=computed(()=>cart.value.length)
const paymentDifference=computed(()=>Number((total.value-(Number(cashAmount.value)||0)-(Number(qrAmount.value)||0)).toFixed(2)))
// Stock que queda para el producto abierto en el diálogo, descontando lo que ya está en el carrito.
const availableStock=computed(()=>{if(!selectedProduct.value)return 0;const item=cart.value.find(i=>i.id===selectedProduct.value.id);return Math.max(0,Number((Number(selectedProduct.value.stock_inicial)-Number(item?.cantidad||0)).toFixed(3)))})
watch([paymentType,total],()=>{if(paymentType.value==='EFECTIVO'){cashAmount.value=total.value;qrAmount.value=0}else if(paymentType.value==='QR'){cashAmount.value=0;qrAmount.value=total.value}else if(Number(cashAmount.value)+Number(qrAmount.value)===0){cashAmount.value=total.value;qrAmount.value=0}})
async function loadProducts(){loadingProducts.value=true;highlighted.value=-1;try{const {data}=await proxy.$axios.get('/productos',{params:{q:search.value,categoria_id:category.value?.id,per_page:productsPerPage,page:productsPage.value}});products.value=data.data;productsLastPage.value=data.last_page||1;productsTotal.value=data.total||0;productsFrom.value=data.from||0;productsTo.value=data.to||0;if(productsPage.value>productsLastPage.value){productsPage.value=productsLastPage.value;await loadProducts()}}finally{loadingProducts.value=false}}
function resetProductsPage(){productsPage.value=1;loadProducts()}
// ── Navegación por teclado en la grilla ──────────────────────
// Las columnas son "auto-fill", así que se leen del grid ya renderizado para saber cuánto salta ↑/↓.
function gridColumns(){const el=gridRef.value;if(!el)return 1;return Math.max(1,getComputedStyle(el).gridTemplateColumns.split(' ').filter(Boolean).length)}
function scrollToHighlighted(){requestAnimationFrame(()=>gridRef.value?.children?.[highlighted.value]?.scrollIntoView({block:'nearest'}))}
function focusGrid(){if(!products.value.length)return;highlighted.value=0;gridRef.value?.focus();scrollToHighlighted()}
function backToSearch(){highlighted.value=-1;searchInput.value?.focus()}
function moveHighlight(delta){const last=products.value.length-1;if(last<0)return;highlighted.value=Math.min(last,Math.max(0,highlighted.value+delta));scrollToHighlighted()}
function handleGridKeyboard(event){const columns=gridColumns(),index=highlighted.value;switch(event.key){case 'ArrowRight':event.preventDefault();return moveHighlight(1);case 'ArrowLeft':event.preventDefault();return index<=0?backToSearch():moveHighlight(-1);case 'ArrowDown':event.preventDefault();return moveHighlight(columns);case 'ArrowUp':event.preventDefault();return index<columns?backToSearch():moveHighlight(-columns);case 'Home':event.preventDefault();return moveHighlight(-products.value.length);case 'End':event.preventDefault();return moveHighlight(products.value.length);case 'Enter':event.preventDefault();if(products.value[index])openProductDialog(products.value[index]);return;case 'Escape':event.preventDefault();return backToSearch()}}
function handleSearchInput(value){const code=String(value||'').trim();clearTimeout(productsSearchTimer);if(parseScaleBarcode(code))return;productsSearchTimer=setTimeout(resetProductsPage,250)}
function focusSearch(){setTimeout(()=>searchInput.value?.focus(),50)}
// Suma al carrito respetando el stock disponible (el backend igual valida y responde 422).
function add(product,amount=null){const item=cart.value.find(i=>i.id===product.id),requested=Number(amount??quantityStep(product));if(Number(product.stock_inicial)<=0)return proxy.$alert.error(`${product.nombre}: stock 0. Registra primero una compra para cargar existencias.`);const next=Number(((item?.cantidad||0)+requested).toFixed(3));if(next>Number(product.stock_inicial)+.0001)return proxy.$alert.error(`Stock insuficiente: disponible ${quantity(product.stock_inicial,product.unidad)} ${product.unidad}`);if(item){item.cantidad=next;syncLineTotal(item)}else cart.value.push({...product,cantidad:requested,total_editable:(Number(product.precio_venta)*requested).toFixed(2)});if(amount!==null)proxy.$alert.success(`${product.nombre}: ${quantity(requested,product.unidad)} ${product.unidad} leído correctamente`)}
function openProductDialog(product,amount=null){if(Number(product.stock_inicial)<=0)return proxy.$alert.error(`${product.nombre}: stock 0. Registra primero una compra para cargar existencias.`);selectedProduct.value=product;const item=cart.value.find(i=>i.id===product.id);const free=Math.max(0,Number((Number(product.stock_inicial)-Number(item?.cantidad||0)).toFixed(3)));if(free<minimumQty(product))return proxy.$alert.error(`${product.nombre}: ya agregaste todo el stock disponible al carrito`);quickQuantity.value=amount??Math.min(1,free);quickPrice.value=Number((canEditPrice.value?item?.precio_venta:null)??product.precio_venta??0);productDialog.value=true}
function confirmProduct(){const product=selectedProduct.value,requested=Number(quickQuantity.value),price=canEditPrice.value?Number(quickPrice.value):Number(product?.precio_venta||0);if(!product||!(requested>=minimumQty(product)))return proxy.$alert.error('Ingrese una cantidad válida');if(!(price>=0)||quickPrice.value==='')return proxy.$alert.error('Ingrese un precio válido');if(requested>availableStock.value+.0001)return proxy.$alert.error(`Stock insuficiente: disponible ${quantity(availableStock.value,product.unidad)} ${product.unidad}`);const rounded=isWeighted(product)?Math.round(requested*1000)/1000:Math.floor(requested);const item=cart.value.find(i=>i.id===product.id);if(item){item.cantidad=Number((Number(item.cantidad)+rounded).toFixed(3));item.precio_venta=price;syncLineTotal(item)}else cart.value.push({...product,cantidad:rounded,precio_venta:price,total_editable:(price*rounded).toFixed(2)});productDialog.value=false;selectedProduct.value=null;search.value='';productsPage.value=1;loadProducts()}
function parseScaleBarcode(value){const code=String(value||'').trim();if(!/^2\d{12}$/.test(code))return null;const expected=ean13CheckDigit(code.slice(0,12));if(expected!==Number(code[12]))return null;return{productCode:code.slice(0,7),weight:Number(code.slice(7,12))/1000}}
function ean13CheckDigit(firstTwelve){const sum=[...firstTwelve].reduce((total,digit,index)=>total+Number(digit)*(index%2===0?1:3),0);return(10-(sum%10))%10}
function exactProduct(code,list=products.value){const normalized=String(code||'').trim().toUpperCase();return list.find(product=>String(product.codigo||'').trim().toUpperCase()===normalized||String(product.codigo_barras||'').trim().toUpperCase()===normalized)}
function addScannedProduct(product,amount=null){add(product,amount??(isWeighted(product)?null:1));search.value='';productsPage.value=1;loadProducts();searchInput.value?.focus()}
// Enter en el buscador: si hay texto busca coincidencia exacta (o etiqueta de balanza); si está vacío pasa al cobro.
async function addExact(showMissing=true,inputValue=null){const q=String(inputValue??search.value??'').trim().toUpperCase();if(!q){if(cart.value.length)openCheckout();return}if(processingBarcode)return;processingBarcode=true;try{const scale=parseScaleBarcode(q);const lookup=scale?.productCode||q;let product=exactProduct(lookup);if(!product){const{data}=await proxy.$axios.get('/productos',{params:{q:lookup,per_page:20,page:1}});product=exactProduct(lookup,data.data||[])}if(!product){if(showMissing)proxy.$alert.error(scale?`No existe un producto con código de balanza ${lookup}`:`No existe un producto con código ${q}`);return}if(scale&&product.unidad!=='KG'){proxy.$alert.error(`${product.nombre} debe tener unidad KG`);return}if(scale)addScannedProduct(product,scale.weight);else openProductDialog(product)}catch(e){proxy.$alert.error(e.response?.data?.message||'No se pudo leer el código del producto')}finally{processingBarcode=false}}
function changeQty(item,amount){const next=Number((Number(item.cantidad)+amount).toFixed(3));if(next<minimumQty(item))return removeItem(item);if(next>Number(item.stock_inicial)+.0001)return proxy.$alert.error(`Stock insuficiente: disponible ${quantity(item.stock_inicial,item.unidad)} ${item.unidad}`);item.cantidad=next;syncLineTotal(item)}
function validateQty(item){let value=Number(item.cantidad)||minimumQty(item);value=isWeighted(item)?Math.round(value*1000)/1000:Math.floor(value);const available=Math.max(0,Number(item.stock_inicial));if(value>available){item.cantidad=available;proxy.$alert.error('La cantidad fue ajustada al stock disponible')}else item.cantidad=Math.max(minimumQty(item),value);syncLineTotal(item)}
function syncLineTotal(item){item.total_editable=(Number(item.precio_venta||0)*Number(item.cantidad||0)).toFixed(2)}
function applyLineTotal(item){if(!canEditPrice.value)return syncLineTotal(item);const totalValue=Math.max(0,Number(item.total_editable)||0),lineQuantity=Math.max(minimumQty(item),Number(item.cantidad)||minimumQty(item));item.total_editable=totalValue.toFixed(2);item.precio_venta=Number((totalValue/lineQuantity).toFixed(4))}
function removeItem(item){cart.value=cart.value.filter(i=>i.id!==item.id)}
function clearCart(){proxy.$alert.dialog('¿Eliminar todos los productos del carrito?').onOk(()=>{cart.value=[];proxy.$alert.success('Carrito vaciado');searchInput.value?.focus()})}
function openCheckout(){cart.value.forEach(item=>{const requestedTotal=item.total_editable;validateQty(item);item.total_editable=requestedTotal;applyLineTotal(item)});if(cart.value.some(i=>Number(i.precio_venta)<0||i.precio_venta===''))return proxy.$alert.error('Revisa los precios de venta');checkoutOpenedAt=Date.now();checkoutDialog.value=true}
// Enter confirma el cobro; el margen de 250 ms evita que el Enter que abrió el diálogo lo dispare.
function handleSaleKeyboard(event){if(event.key!=='Enter'||!checkoutDialog.value||productDialog.value||saving.value||Date.now()-checkoutOpenedAt<250)return;event.preventDefault();event.stopPropagation();confirmSale()}
async function confirmSale(){if(saving.value)return;if(!cart.value.length)return proxy.$alert.error('El carrito está vacío');if(paymentType.value==='COMBINADO'&&paymentDifference.value!==0)return proxy.$alert.error('Efectivo y QR deben sumar el total');saving.value=true;try{const {data}=await proxy.$axios.post('/ventas',{descuento:validDiscount.value,caja:cashRegister.value,tipo_pago:paymentType.value,monto_efectivo:cashAmount.value,monto_qr:qrAmount.value,observacion:observation.value,detalles:cart.value.map(i=>({producto_id:i.id,cantidad:i.cantidad,precio_venta:i.precio_venta}))});checkoutDialog.value=false;proxy.$alert.success(`Venta ${data.numero} registrada`);printSale(data);cart.value=[];discount.value=0;observation.value='';paymentType.value='EFECTIVO';loadProducts();searchInput.value?.focus()}catch(e){proxy.$alert.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||e.response?.data?.message||'No se pudo registrar la venta')}finally{saving.value=false}}
onMounted(()=>{window.addEventListener('keydown',handleSaleKeyboard);loadProducts();proxy.$axios.get('/productos-catalogos').then(r=>categories.value=r.data.categorias)})
onBeforeUnmount(()=>{window.removeEventListener('keydown',handleSaleKeyboard);clearTimeout(productsSearchTimer)})
</script>

<style scoped>
.product-loading{min-height:350px}
.section-title{font-size:12px;font-weight:700;color:#546e7a;display:flex;align-items:center;gap:4px;margin-bottom:4px}
.product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(125px,1fr));gap:6px;max-height:calc(100vh - 180px);overflow:auto}.product-grid:focus{outline:none}.product-card{transition:.15s;overflow:hidden}.product-card:hover{border-color:#f57c00;transform:translateY(-1px)}.product-card--empty{opacity:.55}.product-card--active{border-color:#f57c00;box-shadow:0 0 0 2px rgba(245,124,0,.45);transform:translateY(-1px)}.product-image{height:60px;background:#fffaf3;display:flex;align-items:center;justify-content:center}.product-image img{width:100%;height:100%;object-fit:contain}.product-name{height:32px;line-height:16px}.cart-card{position:sticky;top:62px}.cart-list{max-height:55vh;overflow:auto}.cart-item{min-height:58px;padding-top:3px;padding-bottom:3px}.cart-thumb{min-width:34px;padding-right:6px}.cart-item-content{min-width:0}.cart-item-name{line-height:14px;margin-bottom:2px}.cart-fields-row{display:flex;align-items:flex-end;gap:6px;white-space:nowrap}.price-label,.total-label{font-size:10px;line-height:11px;color:#607d8b;display:flex;flex-direction:column;gap:2px;margin:0}.price-input,.qty-input,.total-input{height:26px;border:1px solid #cfd8dc;border-radius:4px;padding:2px 6px;font-size:13px;color:#263238;background:#fff}.qty-input{width:78px}.price-input{width:94px}.total-input{width:98px;font-weight:700;color:#f57c00}.cart-actions{display:flex;align-items:center;margin-left:auto}.cart-actions .q-btn{min-width:24px;min-height:24px}.summary-table{max-height:125px;overflow:auto}.caja-toggle{border:1px solid #cfd8dc;border-radius:6px;overflow:hidden}.price-input:focus,.qty-input:focus,.total-input:focus{outline:1px solid #f57c00;border-color:#f57c00}.input-locked{background:#eceff1;color:#78909c;cursor:not-allowed}.input-locked:focus{outline:none;border-color:#cfd8dc}@media(max-width:1023px){.cart-card{position:static}.product-grid{max-height:none}}@media(max-width:420px){.cart-thumb{display:none}.cart-fields-row{gap:3px;overflow-x:auto}.cart-actions .q-btn{min-width:20px;padding:0}.qty-input{width:68px}.price-input{width:78px}.total-input{width:82px}}
</style>
