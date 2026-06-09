<template>
  <q-page padding>
    <div v-if="destinationStore.loading" class="row justify-center q-py-xl">
      <q-spinner-dots color="primary" size="4em" />
    </div>

    <div v-else-if="!destination" class="text-center q-py-xl">
      <q-icon name="error_outline" size="64px" color="negative" />
      <div class="text-h5 q-mt-md">Destination not found</div>
      <q-btn color="primary" label="Back to Destinations" to="/destinations" class="q-mt-lg" />
    </div>

    <div v-else>
      <q-btn 
        flat 
        color="primary" 
        icon="arrow_back" 
        label="Back to Destinations" 
        @click="$router.push('/destinations')" 
        class="q-mb-md" 
      />

      <q-img
        :src="destination.cover_image || 'https://via.placeholder.com/1200x500?text=No+Image'"
        ratio="2.4"
        class="rounded-borders shadow-2 q-mb-lg bg-grey-3"
      />

      <div class="row items-center justify-between q-mb-md">
        <div class="text-h4 text-weight-bold">{{ destination.name }}</div>
        <div class="q-gutter-sm">
          <q-badge color="primary" class="q-pa-sm text-subtitle2">{{ destination.province }}</q-badge>
          <q-badge color="primary" outline class="q-pa-sm text-subtitle2">{{ destination.region }}</q-badge>
          <q-badge color="secondary" class="q-pa-sm text-subtitle2 capitalize">{{ destination.type }}</q-badge>
        </div>
      </div>

      <div class="row items-center text-grey-8 q-mb-lg">
        <q-icon name="calendar_today" size="sm" class="q-mr-sm" />
        <span class="text-subtitle1">Best time to visit: <strong class="text-black">{{ destination.best_season }}</strong></span>
      </div>

      <div class="text-body1 text-justify q-mb-xl text-grey-9" style="line-height: 1.8;">
        {{ destination.description }}
      </div>

      <q-card v-if="destination.travel_tips" flat bordered class="bg-blue-grey-1 q-mb-xl">
        <q-card-section>
          <div class="row items-center q-mb-sm">
            <q-icon name="lightbulb" color="amber-8" size="sm" class="q-mr-sm" />
            <div class="text-h6">Travel Tips</div>
          </div>
          <div class="text-body2 text-grey-9">{{ destination.travel_tips }}</div>
        </q-card-section>
      </q-card>

      <div v-if="destination.coordinates && destination.coordinates.lat" class="row items-center text-grey-7 q-mb-lg bg-grey-2 q-pa-md rounded-borders">
        <q-icon name="place" size="sm" color="red" class="q-mr-sm" />
        <span class="text-subtitle2">
          <strong>Coordinates:</strong> Lat {{ destination.coordinates.lat }}, Lng {{ destination.coordinates.lng }}
        </span>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDestinationStore } from 'src/stores/destinationStore'

const route = useRoute()
const router = useRouter() // eslint-disable-line no-unused-vars
const destinationStore = useDestinationStore()

const destination = computed(() => destinationStore.currentDestination)

onMounted(async () => {
  const slug = route.params.slug
  if (slug) {
    try {
      await destinationStore.fetchDestination(slug)
    } catch (e) {
      console.error(e)
    }
  }
})
</script>

<style scoped>
.capitalize {
  text-transform: capitalize;
}
</style>
