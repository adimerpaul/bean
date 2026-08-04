<template>
  <q-layout view="lHh Lpr lFf">
    <!-- HEADER -->
    <q-header class="app-header">
      <q-toolbar>
        <q-btn
          flat
          color="primary"
          :icon="leftDrawerOpen ? 'keyboard_double_arrow_left' : 'keyboard_double_arrow_right'"
          aria-label="Menu"
          @click="toggleLeftDrawer"
          unelevated
          dense
        />
        <div class="row items-center q-gutter-sm">
          <div class="text-subtitle1 text-weight-medium" style="line-height: 0.9">
            {{ companyName }}
          </div>
        </div>

        <q-space />

        <q-btn-dropdown flat unelevated no-caps dropdown-icon="expand_more">
          <template v-slot:label>
            <div class="header-user row items-center no-wrap">
              <q-avatar rounded size="30px" style="border:2px solid #e4eae7">
                <img :src="$store.user.avatar ? $imgBase + '/images/' + $store.user.avatar : $imgBase + '/images/default.png'"
                     style="object-fit:cover;width:100%;height:100%"
                     @error="$event.target.src = $imgBase + '/images/default.png'" />
              </q-avatar>
              <div class="text-left" style="line-height: 1">
                <div class="ellipsis" style="max-width: 130px;">
                  {{ $store.user.username }}
                </div>
              </div>
            </div>
          </template>

          <q-separator />

          <q-item clickable v-ripple @click="logout" v-close-popup>
            <q-item-section avatar>
              <q-icon name="logout" />
            </q-item-section>
            <q-item-section>
              <q-item-label>Salir</q-item-label>
            </q-item-section>
          </q-item>
        </q-btn-dropdown>
      </q-toolbar>
    </q-header>

    <!-- DRAWER -->
    <q-drawer
      v-model="leftDrawerOpen"
      bordered
      show-if-above
      :width="236"
      :breakpoint="700"
      class="app-drawer text-white"
    >
      <q-scroll-area class="fit">
        <div class="drawer-shell">
          <div class="drawer-brand">
            <div class="drawer-brand__logo">
              <img :src="companyLogo" :alt="companyName" />
            </div>
            <div class="drawer-brand__text">
              <div class="drawer-brand__title">{{ companyName }}</div>
              <div class="drawer-brand__caption">Ventas de pollo y alimentos</div>
            </div>
          </div>

          <div class="drawer-eyebrow">Módulos</div>

          <q-list dense class="drawer-menu">
            <q-item
              v-for="link in visibleLinks"
              :key="link.title"
              dense
              clickable
              :to="link.link"
              exact
              class="drawer-menu-link"
              active-class="drawer-menu-link--active"
            >
              <q-item-section avatar class="drawer-menu-link__avatar">
                <q-icon :name="link.icon" size="15px" />
              </q-item-section>
              <q-item-section>
                <q-item-label class="drawer-menu-link__label" lines="1">{{ link.title }}</q-item-label>
              </q-item-section>
            </q-item>
          </q-list>

          <div class="drawer-footer">
            <div>Bean v{{ $version }}</div>
            <div>© {{ new Date().getFullYear() }} Bean</div>
          </div>

          <q-item clickable class="drawer-logout" @click="logout">
            <q-item-section avatar class="drawer-menu-link__avatar">
              <q-icon name="logout" />
            </q-item-section>
            <q-item-section>
              <q-item-label>Salir</q-item-label>
            </q-item-section>
          </q-item>
        </div>
      </q-scroll-area>
    </q-drawer>

    <!-- PAGE -->
    <q-page-container class="page-bg">
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { computed, getCurrentInstance, onMounted, ref } from 'vue'

const { proxy } = getCurrentInstance()

const leftDrawerOpen = ref(false)
const cachedCompany = JSON.parse(localStorage.getItem('empresaBean') || '{}')
const companyName = ref(cachedCompany.nombre_empresa || 'Bean')
const companyLogo = ref(cachedCompany.logo_url || '/bean-logo.svg')

const links = [
  { title: 'Inicio',    icon: 'dashboard',   link: '/',         can: null },
  { title: 'Usuarios',  icon: 'people',      link: '/usuarios', can: 'Ver Usuarios' },
  { title: 'Productos', icon: 'inventory_2', link: '/productos', can: 'Ver Productos' },
  { title: 'Nueva venta', icon: 'point_of_sale', link: '/ventas/nueva', can: 'Crear Ventas' },
  { title: 'Ventas', icon: 'receipt_long', link: '/ventas', can: 'Ver Ventas' },
  { title: 'Nueva compra', icon: 'add_business', link: '/compras/nueva', can: 'Crear Compras' },
  { title: 'Compras', icon: 'shopping_bag', link: '/compras', can: 'Ver Compras' },
  { title: 'Proveedores', icon: 'groups', link: '/proveedores', can: 'Ver Compras' },
  { title: 'Nueva baja', icon: 'remove_circle_outline', link: '/bajas/nueva', can: 'Crear Bajas' },
  { title: 'Bajas', icon: 'delete_forever', link: '/bajas', can: 'Ver Bajas' },
  { title: 'Por vencer', icon: 'schedule', link: '/productos/por-vencer', can: 'Ver Compras' },
  { title: 'Vencidos', icon: 'event_busy', link: '/productos/vencidos', can: 'Ver Compras' },
  { title: 'Configuración', icon: 'settings', link: '/configuracion', can: 'Gestionar Configuración' },
]

const visibleLinks = computed(() =>
  links.filter(link => link.can === null || proxy.$store.hasPermission(link.can))
)

function toggleLeftDrawer () {
  leftDrawerOpen.value = !leftDrawerOpen.value
}

onMounted(() => {
  proxy.$axios.get('/configuracion').then(({ data }) => {
    data.logo_url = data.logo ? `${proxy.$imgBase}/images/${data.logo}` : null
    companyName.value = data.nombre_empresa || 'Bean'
    companyLogo.value = data.logo_url || '/bean-logo.svg'
    localStorage.setItem('empresaBean', JSON.stringify(data))
  })
})

function logout () {
  proxy.$alert.dialog('¿Desea salir del sistema?').onOk(() => {
    proxy.$axios.post('/logout').finally(() => {
      proxy.$store.logout()
      localStorage.removeItem('tokenBean')
      localStorage.removeItem('permissionsBean')
      localStorage.removeItem('user')
      delete proxy.$axios.defaults.headers.common['Authorization']
      proxy.$router.push('/login')
    })
  })
}
</script>

<style>
.app-drawer {
  background: linear-gradient(180deg, #f57c00 0%, #222222 55%, #0f0f0f 100%);
  color: #ffffff;
}

.app-drawer,
.app-drawer .q-drawer__content,
.app-drawer .q-scrollarea,
.app-drawer .q-scrollarea__container,
.app-drawer .q-scrollarea__content {
  background: linear-gradient(180deg, #f57c00 0%, #222222 55%, #0f0f0f 100%) !important;
}

.drawer-shell {
  min-height: 100%;
  padding: 6px 6px 8px;
}

.drawer-brand {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 5px 6px;
  margin-bottom: 4px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.08);
}

.drawer-brand__logo {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  flex-shrink: 0;
  border-radius: 10px;
  background: linear-gradient(135deg, #ffb300, #f57c00);
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
  overflow: hidden;
}

.drawer-brand__logo img {
  width: 76px;
  height: 32px;
  max-width: none;
  object-fit: cover;
  object-position: left;
}

.drawer-brand__title {
  color: #ffffff;
  font-size: 12.5px;
  font-weight: 700;
  letter-spacing: -0.01em;
  line-height: 1.1;
}

.drawer-brand__text {
  min-width: 0;
  line-height: 1.05;
}

.drawer-brand__caption {
  margin-top: 2px;
  color: rgba(255, 255, 255, 0.72);
  font-size: 10px;
  line-height: 1.15;
}

.drawer-eyebrow {
  padding: 2px 8px 3px;
  color: rgba(255, 255, 255, 0.66);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.drawer-menu {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.drawer-menu-link {
  min-height: 26px;
  margin: 0 4px;
  padding: 0 6px;
  border-radius: 7px;
  color: rgba(255, 255, 255, 0.86);
}

.drawer-menu-link__avatar {
  min-width: 22px;
}

.drawer-menu-link__label {
  font-size: 11px;
  font-weight: 650;
  line-height: 1.1;
}

.drawer-menu-link--active {
  background: linear-gradient(135deg, #fb8c00, #1b1b1b);
  color: #ffffff !important;
  box-shadow: inset 3px 0 0 #ffe0b2;
}

.drawer-footer {
  padding: 6px 8px 4px;
  margin-top: 6px;
  color: rgba(255, 255, 255, 0.58);
  font-size: 10px;
  line-height: 1.35;
}

.drawer-logout {
  min-height: 24px;
  margin: 2px 5px 0;
  border-radius: 9px;
  color: #ffebee;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.12);
}

.app-header {
  background: #ffffff;
  border-bottom: 1px solid #e4eae7;
  color: #16241f;
}

.app-header .q-toolbar {
  min-height: 54px;
}

.header-user {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #fff7ed;
  border-radius: 99px;
  padding: 4px 12px 4px 5px;
}

.page-bg {
  background: #fffaf3;
}
</style>
