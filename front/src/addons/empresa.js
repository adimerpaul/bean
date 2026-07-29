export function companyData () {
  try {
    return JSON.parse(localStorage.getItem('empresaBean') || '{}')
  } catch {
    return {}
  }
}
