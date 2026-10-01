/**
 * Sesión guardada en localStorage: token, usuario y permisos. Con eso la app abre
 * y arma el menú sin consultar al servidor, que es lo que permite vender sin internet.
 */
const TOKEN = 'tokenBean'
const USER = 'user'
const PERMS = 'permissionsBean'

export function sessionToken () {
  return localStorage.getItem(TOKEN)
}

function leer (key, fallback) {
  try {
    return JSON.parse(localStorage.getItem(key) || 'null') ?? fallback
  } catch {
    return fallback
  }
}

export function sessionUser () {
  return leer(USER, {})
}

export function sessionPermissions () {
  const perms = leer(PERMS, [])

  return Array.isArray(perms) ? perms : []
}

/** Guarda todo lo necesario para entrar sin internet. Devuelve la lista de permisos. */
export function saveSession (user, token = null) {
  const perms = (user?.permissions || []).map(p => p.name)
  if (token) localStorage.setItem(TOKEN, token)
  localStorage.setItem(USER, JSON.stringify(user || {}))
  localStorage.setItem(PERMS, JSON.stringify(perms))

  return perms
}

export function clearSession () {
  [TOKEN, USER, PERMS].forEach(key => localStorage.removeItem(key))
}
