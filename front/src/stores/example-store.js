import { defineStore, acceptHMRUpdate } from 'pinia'

// Carritos de venta: se atienden varios clientes a la vez desde la misma terminal.
// Viven en el store (no en la página) para no perderse al cambiar de pantalla.
export const CART_COUNT = 5

const emptyCart = () => ({ items: [], descuento: 0, observacion: '' })
// Borrador de compra: lo que se está armando en /compras/nueva; se limpia al guardar.
const emptyCompra = () => ({ items: [], proveedor: null, numero_factura: '', comentario: '' })

export const useCounterStore = defineStore('counter', {
  state: () => ({
    isLogged: false,
    user: {},
    permissions: [],
    carts: Array.from({ length: CART_COUNT }, emptyCart),
    activeCart: 0,
    compra: emptyCompra(),
  }),

  getters: {
    hasPermission: (state) => (perm) => {
      if (Array.isArray(perm)) return perm.some(p => state.permissions.includes(p))
      return state.permissions.includes(perm)
    },
    currentCart: (state) => state.carts[state.activeCart] || state.carts[0],
    cartTotal: (state) => (index) => {
      const cart = state.carts[index]
      if (!cart) return 0
      const subtotal = cart.items.reduce((sum, i) => sum + Number(i.precio_venta || 0) * Number(i.cantidad || 0), 0)
      return Math.max(0, subtotal - Math.min(Math.max(Number(cart.descuento) || 0, 0), subtotal))
    },
    pendingCarts: (state) => state.carts.filter(c => c.items.length).length,
  },

  actions: {
    setActiveCart (index) {
      if (!Number.isInteger(index) || index < 0 || index >= CART_COUNT) return
      this.activeCart = index
    },
    resetCart (index = this.activeCart) {
      if (!this.carts[index]) return
      this.carts[index] = emptyCart()
    },
    resetCompra () {
      this.compra = emptyCompra()
    },
    logout () {
      this.isLogged = false
      this.user = {}
      this.permissions = []
      this.carts = Array.from({ length: CART_COUNT }, emptyCart)
      this.activeCart = 0
      this.compra = emptyCompra()
    },
  },
})

if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(useCounterStore, import.meta.hot))
}
