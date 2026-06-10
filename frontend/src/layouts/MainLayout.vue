<template>
  <q-layout view="lHh Lpr lFf">
    <q-header elevated class="bg-primary text-white">
      <q-toolbar>
        <q-btn flat dense round icon="menu" aria-label="Menu" @click="toggleDrawer" />
        <q-toolbar-title class="text-weight-bold" style="font-family: 'Poppins', sans-serif;">
          Mera Musafir
        </q-toolbar-title>

        <q-btn flat dense round to="/trips/create" icon="add_circle_outline" aria-label="Create Trip">
          <q-tooltip>Create Trip</q-tooltip>
        </q-btn>

        <q-btn flat dense round :to="`/profile`" class="q-ml-xs">
          <q-avatar size="32px">
            <img v-if="authStore.user?.avatar" :src="authStore.user.avatar" />
            <q-icon v-else name="person" />
          </q-avatar>
        </q-btn>
      </q-toolbar>
    </q-header>

    <q-drawer v-model="drawerOpen" show-if-above bordered :width="240">
      <div class="q-pa-md bg-primary text-white">
        <div class="text-h6 text-weight-bold" style="font-family: 'Poppins', sans-serif;">Mera Musafir</div>
        <div class="text-caption opacity-70" v-if="authStore.user">{{ authStore.user.name }}</div>
      </div>

      <q-list padding class="q-mt-sm">
        <q-item clickable v-ripple to="/" exact>
          <q-item-section avatar><q-icon name="home" /></q-item-section>
          <q-item-section>Home</q-item-section>
        </q-item>

        <q-item clickable v-ripple to="/destinations">
          <q-item-section avatar><q-icon name="travel_explore" /></q-item-section>
          <q-item-section>Explore</q-item-section>
        </q-item>

        <q-separator class="q-my-sm" />

        <q-item-label header class="text-caption text-grey-6">TRIPS</q-item-label>

        <q-item clickable v-ripple to="/trips">
          <q-item-section avatar><q-icon name="hiking" /></q-item-section>
          <q-item-section>Browse Trips</q-item-section>
        </q-item>

        <q-item clickable v-ripple to="/trips/create">
          <q-item-section avatar><q-icon name="add_circle_outline" /></q-item-section>
          <q-item-section>Create Trip</q-item-section>
        </q-item>

        <q-item clickable v-ripple to="/my-trips">
          <q-item-section avatar><q-icon name="luggage" /></q-item-section>
          <q-item-section>My Trips</q-item-section>
        </q-item>

        <q-separator class="q-my-sm" />

        <q-item-label header class="text-caption text-grey-6">ACCOUNT</q-item-label>

        <q-item clickable v-ripple to="/profile">
          <q-item-section avatar><q-icon name="person" /></q-item-section>
          <q-item-section>Profile</q-item-section>
        </q-item>

        <q-item clickable v-ripple @click="handleLogout">
          <q-item-section avatar><q-icon name="logout" color="negative" /></q-item-section>
          <q-item-section><span class="text-negative">Logout</span></q-item-section>
        </q-item>
      </q-list>
    </q-drawer>

    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from 'src/stores/authStore'

const router = useRouter()
const authStore = useAuthStore()

const drawerOpen = ref(false)

const toggleDrawer = () => {
  drawerOpen.value = !drawerOpen.value
}

const handleLogout = async () => {
  await authStore.logout()
  router.push('/login')
}
</script>
