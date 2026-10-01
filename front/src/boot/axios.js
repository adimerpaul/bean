import { defineBoot } from '#q-app/wrappers'
import axios from 'axios'
import { Alert } from '../addons/Alert'
import { companyData } from '../addons/empresa'
import { clearSession, saveSession, sessionPermissions, sessionToken, sessionUser } from '../addons/sesion'
import { useCounterStore } from '../stores/example-store'

// Be careful when using SSR for cross-request state pollution
// due to creating a Singleton instance here;
// If any client changes this (global) instance, it might be a
// good idea to move this instance creation inside of the
// "export default () => {}" function below (which runs individually
// for each client)
const api = axios.create({ baseURL: 'https://api.example.com' })

export default defineBoot(({ app, router }) => {
  const store = useCounterStore()

  app.config.globalProperties.$axios = axios.create({ baseURL: import.meta.env.VITE_API_BACK })
  app.config.globalProperties.$alert = Alert
  app.config.globalProperties.$store = store
  app.config.globalProperties.$url = import.meta.env.VITE_API_BACK
  app.config.globalProperties.$imgBase = (import.meta.env.VITE_API_BACK || '').replace(/\/api\/?$/, '')
  app.config.globalProperties.$version = import.meta.env.VITE_VERSION
  // Primero lo guardado (los tickets se imprimen igual sin conexión) y luego lo del servidor.
  app.config.globalProperties.$empresa = companyData()
  app.config.globalProperties.$axios.get('/configuracion').then(({ data }) => {
    data.logo_url = data.logo ? `${app.config.globalProperties.$imgBase}/images/${data.logo}` : null
    localStorage.setItem('empresaBean', JSON.stringify(data))
    app.config.globalProperties.$empresa = data
  }).catch(() => { /* sin conexión: se usa la configuración guardada */ })

  function cerrarSesion () {
    clearSession()
    delete app.config.globalProperties.$axios.defaults.headers.common['Authorization']
    store.logout()
    if (router.currentRoute.value.path !== '/login') router.push('/login')
  }

  // Sólo el servidor cierra la sesión: un 401 significa token inválido. Un error de
  // red (sin internet o servidor caído) no toca nada, para poder seguir vendiendo offline.
  app.config.globalProperties.$axios.interceptors.response.use(
    response => {
      store.offline = false

      return response
    },
    error => {
      store.offline = !error.response
      if (error.response?.status === 401 && sessionToken()) cerrarSesion()

      return Promise.reject(error)
    }
  )

  const token = sessionToken()
  if (token) {
    app.config.globalProperties.$axios.defaults.headers.common['Authorization'] = `Bearer ${token}`

    // Usuario y permisos guardados: el menú queda listo sin esperar a /me (y sin internet).
    const cachedUser = sessionUser()
    const cachedPerms = sessionPermissions()
    if (cachedUser.id || cachedPerms.length) {
      store.user = cachedUser
      store.permissions = cachedPerms
      store.isLogged = true
    }

    app.config.globalProperties.$axios.get('me').then(({ data }) => {
      store.isLogged = true
      store.user = data
      store.permissions = saveSession(data)
    }).catch(() => { /* 401 lo maneja el interceptor; sin conexión se sigue con la sesión guardada */ })
  }

  app.config.globalProperties.$api = api
  // ^ ^ ^ this will allow you to use this.$api (for Vue Options API form)
  //       so you can easily perform requests against your app's API
})

export { api }
