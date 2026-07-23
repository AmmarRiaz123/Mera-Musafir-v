<template>
  <q-layout view="lHh Lpr lFf" class="admin-shell">
    <q-header class="admin-header">
      <q-toolbar>
        <q-btn flat dense round icon="menu" class="lt-md" @click="drawer = !drawer" />
        <div class="admin-brand">
          <q-icon name="admin_panel_settings" size="20px" />
          <span>Mera Musafir <b>Admin</b></span>
        </div>
        <q-space />
        <q-btn flat dense no-caps class="admin-exit" icon="logout" label="Exit console" to="/" />
      </q-toolbar>
    </q-header>

    <q-drawer v-model="drawer" :width="230" :breakpoint="1023" show-if-above class="admin-drawer">
      <div class="admin-drawer-inner">
      <div class="admin-who">
        <q-avatar size="38px" class="admin-avatar">
          <img v-if="authStore.user?.avatar" :src="authStore.user.avatar" />
          <span v-else>{{ authStore.user?.name?.[0]?.toUpperCase() }}</span>
        </q-avatar>
        <div class="admin-who-text">
          <span class="admin-who-name">{{ authStore.user?.name }}</span>
          <span class="admin-who-role">Administrator</span>
        </div>
      </div>

      <q-list class="admin-nav">
        <q-item
          v-for="link in links"
          :key="link.to"
          clickable v-ripple
          :to="link.to"
          exact
          active-class="admin-nav--active"
        >
          <q-item-section avatar>
            <q-icon :name="link.icon" size="20px" />
            <q-badge v-if="link.badge && badges[link.badge]" color="red" floating>
              {{ badges[link.badge] }}
            </q-badge>
          </q-item-section>
          <q-item-section>{{ link.label }}</q-item-section>
        </q-item>
      </q-list>
      </div>
    </q-drawer>

    <q-page-container>
      <router-view :key="$route.path" @queues="onQueues" />
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from 'src/stores/authStore'
import { api } from 'src/boot/axios'

const authStore = useAuthStore()
const drawer = ref(true)

const links = [
  { to: '/admin', label: 'Dashboard', icon: 'dashboard' },
  { to: '/admin/users', label: 'Users', icon: 'group' },
  { to: '/admin/agencies', label: 'Agencies', icon: 'verified', badge: 'pending_verification' },
  { to: '/admin/reports', label: 'Reports', icon: 'flag', badge: 'open_reports' },
  { to: '/admin/destinations', label: 'Destinations', icon: 'public' },
  { to: '/admin/broadcast', label: 'Broadcast', icon: 'campaign' },
]

// The two work-queue counts, so the nav badges match the dashboard.
const badges = ref({})
const onQueues = (queues) => { badges.value = queues || {} }

onMounted(async () => {
  try {
    const { data } = await api.get('/api/v1/admin/dashboard')
    badges.value = data.data.queues
  } catch {
    // A non-admin never reaches this layout (route guard), so a failure here
    // is transient — leave the badges empty.
  }
})
</script>

<style scoped>
.admin-shell { background: #f4f5fb; }

.admin-header {
  background: linear-gradient(120deg, #1a1030, #2b1b45 60%, #3a1f57);
  color: #fff;
}
.admin-brand { display: flex; align-items: center; gap: 8px; font-size: 15px; letter-spacing: 0.2px; }
.admin-brand b { font-weight: 700; }
.admin-exit { color: rgba(255, 255, 255, 0.82); }

.admin-drawer :deep(.q-drawer__content) { background: #1e1533; }
.admin-drawer-inner { background: #1e1533; min-height: 100%; }
.admin-who {
  display: flex; align-items: center; gap: 11px;
  padding: 16px 16px 14px; border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.admin-avatar {
  background: linear-gradient(135deg, #8b5cf6, #6d28d9);
  color: #fff; font-weight: 700; font-size: 15px;
}
.admin-who-text { display: flex; flex-direction: column; line-height: 1.2; }
.admin-who-name { color: #fff; font-size: 13.5px; font-weight: 600; }
.admin-who-role {
  font-size: 10.5px; letter-spacing: 0.06em; text-transform: uppercase;
  color: #a78bda;
}

.admin-nav { padding: 8px; }
.admin-nav :deep(.q-item) {
  border-radius: 10px; margin-bottom: 2px; min-height: 44px;
  color: rgba(255, 255, 255, 0.72);
}
.admin-nav :deep(.q-item:hover) { background: rgba(255, 255, 255, 0.05); color: #fff; }
.admin-nav :deep(.admin-nav--active) {
  background: linear-gradient(135deg, #7c3aed, #6d28d9);
  color: #fff; font-weight: 600;
}
.admin-nav :deep(.q-item__section--avatar) { min-width: 34px; position: relative; }
</style>
