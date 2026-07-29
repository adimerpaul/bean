<template>
  <q-layout class="login-layout">
    <q-page-container>
      <q-page class="full-height">
        <div class="login-bg-overlay"></div>

        <div class="login-wrap">
          <q-card flat bordered class="login-card">

            <q-card-section class="q-pt-lg text-center">
              <img :src="companyLogo" :alt="companyName" class="login-logo q-mb-sm" />
              <div class="text-subtitle2 text-grey-7">
                <b>{{ companyName }}</b> · Ventas e inventario
              </div>
            </q-card-section>

            <q-separator spaced />

            <!-- ── Login ── -->
            <template v-if="vista === 'login'">
              <q-card-section class="q-pt-none">
                <q-form @submit.prevent="login">
                  <div class="text-h6 text-bold q-mb-xs">Iniciar sesión</div>
                  <div class="text-body2 text-grey-7 q-mb-md">
                    Accede al panel usando tus credenciales.
                  </div>

                  <div class="q-mb-sm text-caption text-grey-7">Nombre de usuario</div>
                  <q-input v-model="username" outlined dense placeholder="Nombre de usuario"
                           :rules="[v => !!v || 'Ingrese su nombre de usuario']" class="q-mb-md">
                    <template #prepend><q-icon name="account_circle" size="18px" /></template>
                  </q-input>

                  <div class="q-mb-sm text-caption text-grey-7">Contraseña</div>
                  <q-input v-model="password" outlined dense
                           :type="showPassword ? 'text' : 'password'" placeholder="Contraseña"
                           :rules="[v => !!v || 'Ingrese su contraseña']" class="q-mb-md">
                    <template #prepend><q-icon name="lock" size="18px" /></template>
                    <template #append>
                      <q-icon :name="showPassword ? 'visibility' : 'visibility_off'"
                              size="18px" class="cursor-pointer" @click="showPassword = !showPassword" />
                    </template>
                  </q-input>

                  <q-btn color="primary" label="Iniciar sesión" class="full-width"
                         no-caps unelevated size="16px" :loading="loading" type="submit" />
                </q-form>
              </q-card-section>

              <q-card-section class="q-pt-none text-center">
                <q-separator spaced />
                <div class="text-caption text-grey-6">
                  © {{ year }} {{ companyName }}. Todos los derechos reservados.
                </div>
              </q-card-section>
            </template>

            <!-- ── Cambiar contraseña obligatorio ── -->
            <template v-else-if="vista === 'cambiar'">
              <q-card-section class="q-pt-none">
                <div class="row items-center q-mb-xs">
                  <q-icon name="lock_reset" color="warning" size="22px" class="q-mr-sm" />
                  <span class="text-h6 text-bold">Cambia tu contraseña</span>
                </div>
                <q-banner rounded class="bg-orange-1 text-orange-9 q-mb-md" dense>
                  <template #avatar><q-icon name="warning" color="warning" /></template>
                  Por seguridad debes establecer una nueva contraseña antes de continuar.
                </q-banner>

                <q-form @submit.prevent="cambiarPassword">
                  <div class="q-mb-sm text-caption text-grey-7">Nueva contraseña</div>
                  <q-input v-model="newPassword" outlined dense
                           :type="showNewPassword ? 'text' : 'password'" placeholder="Nueva contraseña"
                           :rules="[v => !!v || 'Campo requerido', v => v.length >= 6 || 'Mínimo 6 caracteres']"
                           class="q-mb-md">
                    <template #prepend><q-icon name="lock" size="18px" /></template>
                    <template #append>
                      <q-icon :name="showNewPassword ? 'visibility' : 'visibility_off'"
                              size="18px" class="cursor-pointer" @click="showNewPassword = !showNewPassword" />
                    </template>
                  </q-input>

                  <div class="q-mb-sm text-caption text-grey-7">Repetir nueva contraseña</div>
                  <q-input v-model="newPasswordConfirm" outlined dense
                           :type="showNewPasswordConfirm ? 'text' : 'password'" placeholder="Repetir nueva contraseña"
                           :rules="[v => !!v || 'Campo requerido', v => v === newPassword || 'Las contraseñas no coinciden']"
                           class="q-mb-md">
                    <template #prepend><q-icon name="lock_reset" size="18px" /></template>
                    <template #append>
                      <q-icon :name="showNewPasswordConfirm ? 'visibility' : 'visibility_off'"
                              size="18px" class="cursor-pointer" @click="showNewPasswordConfirm = !showNewPasswordConfirm" />
                    </template>
                  </q-input>

                  <q-btn color="primary" label="Guardar y entrar" class="full-width"
                         no-caps unelevated size="16px" icon="check_circle"
                         :loading="loadingChange" type="submit" />
                </q-form>
              </q-card-section>
            </template>

          </q-card>
        </div>
      </q-page>
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { ref, computed, getCurrentInstance, onMounted } from 'vue'

const { proxy } = getCurrentInstance()

const vista                  = ref('login')
const username               = ref('')
const password               = ref('')
const showPassword           = ref(false)
const loading                = ref(false)

const newPassword            = ref('')
const newPasswordConfirm     = ref('')
const showNewPassword        = ref(false)
const showNewPasswordConfirm = ref(false)
const loadingChange          = ref(false)
let   tempToken              = ''

const year = computed(() => new Date().getFullYear())
const cachedCompany = JSON.parse(localStorage.getItem('empresaBean') || '{}')
const companyName = ref(cachedCompany.nombre_empresa || 'Bean')
const companyLogo = ref(cachedCompany.logo_url || '/bean-logo.svg')

onMounted(() => {
  proxy.$axios.get('/configuracion').then(({ data }) => {
    data.logo_url = data.logo ? `${proxy.$imgBase}/images/${data.logo}` : null
    companyName.value = data.nombre_empresa || 'Bean'
    companyLogo.value = data.logo_url || '/bean-logo.svg'
    localStorage.setItem('empresaBean', JSON.stringify(data))
  })
})

function login () {
  loading.value = true
  proxy.$axios.post('/login', { username: username.value, password: password.value })
    .then(res => {
      const { user, token, must_change_password } = res.data
      proxy.$axios.defaults.headers.common.Authorization = `Bearer ${token}`
      proxy.$store.user = user

      if (must_change_password) {
        tempToken = token
        newPassword.value = ''
        newPasswordConfirm.value = ''
        vista.value = 'cambiar'
      } else {
        const perms = (user.permissions || []).map(p => p.name)
        proxy.$store.isLogged    = true
        proxy.$store.permissions = perms
        localStorage.setItem('tokenBean', token)
        localStorage.setItem('permissionsBean', JSON.stringify(perms))
        localStorage.setItem('user', JSON.stringify(user))
        proxy.$alert.success('Bienvenido ' + user.name)
        proxy.$router.push('/')
      }
    })
    .catch(err => {
      proxy.$alert.error(err?.response?.data?.message || 'Error de autenticación')
    })
    .finally(() => { loading.value = false })
}

function cambiarPassword () {
  loadingChange.value = true
  proxy.$axios.put('cambiar-password', {
    password_actual:             '123456',
    password_nuevo:              newPassword.value,
    password_nuevo_confirmation: newPasswordConfirm.value,
  })
    .then(() => {
      const user = proxy.$store.user
      const perms = (user.permissions || []).map(p => p.name)
      proxy.$store.isLogged    = true
      proxy.$store.permissions = perms
      localStorage.setItem('tokenBean', tempToken)
      localStorage.setItem('permissionsBean', JSON.stringify(perms))
      localStorage.setItem('user', JSON.stringify(user))
      proxy.$alert.success('Contraseña actualizada. ¡Bienvenido!')
      proxy.$router.push('/')
    })
    .catch(err => {
      proxy.$alert.error(err?.response?.data?.message || 'Error al cambiar contraseña')
    })
    .finally(() => { loadingChange.value = false })
}
</script>

<style scoped>
.login-layout {
  background: linear-gradient(135deg, #fb8c00 0%, #1b1b1b 55%, #0f0f0f 100%);
  min-height: 100vh;
}
.full-height { min-height: 100vh; position: relative; }
.login-bg-overlay {
  position: absolute; inset: 0;
  background: radial-gradient(1200px 800px at 70% 40%, rgba(255,255,255,0.08), rgba(0,0,0,0.25));
}
.login-wrap {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 520px;
  margin: 0 auto;
  padding: 24px 12px;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  justify-content: center;
  min-height: 100vh;
}
.login-card {
  width: 100%;
  min-width: 0;
  overflow: hidden;
}
.login-logo { width: 210px; max-width: 80%; height: auto; }
</style>
