<template>
  <q-page padding>
    <!-- Header -->
    <div class="row items-center q-mb-lg">
      <q-btn flat round dense icon="arrow_back" @click="$router.back()" />
      <h1 class="page-title page-title--sm q-ml-sm">Itinerary</h1>
      <q-space />
      <q-btn color="deep-purple" unelevated rounded icon="add" label="Add Day" @click="showAddDay = true" />
    </div>

    <!-- Loading -->
    <div v-if="store.loading" class="row justify-center q-py-xl">
      <q-spinner-dots color="deep-purple" size="3em" />
    </div>

    <!-- Empty state -->
    <div v-else-if="store.itinerary.length === 0" class="text-center q-py-xl">
      <q-icon name="map" size="5em" color="grey-3" />
      <div class="text-h6 text-grey-5 q-mt-md">No itinerary yet</div>
      <div class="text-body2 text-grey-5 q-mt-xs">Add your first day to start planning</div>
      <q-btn class="q-mt-lg" color="deep-purple" unelevated rounded label="Add Day" icon="add" @click="showAddDay = true" />
    </div>

    <!-- Days (sorted by date) -->
    <div v-else>
      <q-card v-for="day in sortedItinerary" :key="day.id" class="q-mb-lg" flat bordered>
        <!-- Day header -->
        <q-card-section class="row items-center justify-between q-py-sm" style="background: #ede7f6;">
          <div>
            <span class="text-subtitle1 text-weight-bold text-deep-purple">Day {{ day.day_number }}</span>
            <span class="text-caption text-grey-7 q-ml-sm">{{ formatDate(day.date) }}</span>
          </div>
          <div class="row q-gutter-xs">
            <q-btn flat round dense icon="add_circle" color="deep-purple" size="sm" @click="openAddItem(day)">
              <q-tooltip>Add item</q-tooltip>
            </q-btn>
            <q-btn flat round dense icon="delete_outline" color="negative" size="sm" @click="confirmDeleteDay(day)">
              <q-tooltip>Delete day</q-tooltip>
            </q-btn>
          </div>
        </q-card-section>

        <!-- Items (sorted by time, nulls last) -->
        <q-list separator v-if="day.items.length">
          <q-item v-for="item in sortedItems(day.items)" :key="item.id" class="q-py-sm">
            <q-item-section avatar style="min-width: 52px">
              <div class="text-caption text-deep-purple text-weight-bold text-center">
                {{ item.time || '—' }}
              </div>
            </q-item-section>
            <q-item-section>
              <q-item-label class="text-weight-medium">{{ item.title }}</q-item-label>
              <q-item-label caption v-if="item.location" class="row items-center">
                <q-icon
                  :name="item.latitude ? 'gps_fixed' : 'place'"
                  size="12px"
                  :color="item.latitude ? 'deep-purple' : 'grey-6'"
                  class="q-mr-xs"
                />{{ item.location }}
              </q-item-label>
              <q-item-label caption v-if="item.notes" class="text-grey-6 q-mt-xs" style="white-space: pre-wrap">
                {{ item.notes }}
              </q-item-label>
            </q-item-section>
            <q-item-section side>
              <div class="row q-gutter-xs">
                <q-btn flat round dense icon="edit" size="sm" color="grey-7" @click="openEditItem(item, day)" />
                <q-btn flat round dense icon="delete" size="sm" color="negative" @click="doDeleteItem(item.id)" />
              </div>
            </q-item-section>
          </q-item>
        </q-list>

        <q-card-section v-else class="text-center text-grey-5 text-caption q-py-md">
          No items yet — click <q-icon name="add_circle" size="14px" /> to add one
        </q-card-section>
      </q-card>
    </div>

    <!-- Add Day dialog -->
    <q-dialog v-model="showAddDay" persistent>
      <q-card style="min-width: 320px">
        <q-card-section>
          <div class="text-h6">Add Day</div>
        </q-card-section>
        <q-card-section>
          <q-input v-model="newDayDate" label="Date" outlined dense>
            <template v-slot:append>
              <q-icon name="event" class="cursor-pointer">
                <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                  <q-date v-model="newDayDate" mask="YYYY-MM-DD" minimal :options="tripDateOptions">
                    <div class="row items-center justify-end">
                      <q-btn v-close-popup label="Close" color="primary" flat />
                    </div>
                  </q-date>
                </q-popup-proxy>
              </q-icon>
            </template>
          </q-input>
          <div v-if="trip" class="text-caption text-grey-6 q-mt-xs q-ml-xs">
            Trip dates: {{ formatDate(trip.start_date) }} – {{ formatDate(trip.end_date) }}
          </div>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup @click="newDayDate = ''" />
          <q-btn flat color="deep-purple" label="Add Day" :loading="submitting" :disable="!newDayDate" @click="submitAddDay" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Add / Edit Item dialog -->
    <q-dialog v-model="showItemDialog" persistent>
      <q-card style="min-width: 340px; max-width: 500px">
        <q-card-section>
          <div class="text-h6">{{ editingItem ? 'Edit Item' : 'Add Item' }}</div>
        </q-card-section>
        <q-card-section class="column q-gutter-sm">
          <q-input v-model="itemForm.title" label="Title *" outlined dense autofocus />

          <!-- Time picker -->
          <q-input v-model="itemForm.time" label="Time" outlined dense clearable>
            <template v-slot:append>
              <q-icon name="access_time" class="cursor-pointer">
                <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                  <q-time v-model="itemForm.time" format24h>
                    <div class="row items-center justify-end">
                      <q-btn v-close-popup label="Close" color="primary" flat />
                    </div>
                  </q-time>
                </q-popup-proxy>
              </q-icon>
            </template>
          </q-input>

          <!-- Location: search + map pin button -->
          <div class="row items-center q-gutter-xs">
            <div class="col">
              <q-select
                ref="locationSelectRef"
                v-model="locationSelected"
                use-input
                fill-input
                hide-selected
                clearable
                input-debounce="500"
                :options="locationOptions"
                option-label="display_name"
                label="Location"
                outlined
                dense
                @filter="searchLocations"
                @clear="clearLocation"
              >
                <template v-slot:prepend>
                  <q-icon name="place" color="grey-6" />
                </template>
                <template v-slot:no-option>
                  <q-item>
                    <q-item-section class="text-grey">
                      {{ locationSearching ? 'Searching…' : 'Type to search Pakistan locations' }}
                    </q-item-section>
                  </q-item>
                </template>
              </q-select>
            </div>
            <q-btn
              round
              unelevated
              color="deep-purple-1"
              text-color="deep-purple"
              icon="pin_drop"
              size="sm"
              @click="openMapDialog"
            >
              <q-tooltip>Choose on map</q-tooltip>
            </q-btn>
          </div>

          <q-input v-model="itemForm.notes" label="Notes" outlined dense type="textarea" :rows="2" />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup @click="resetItemForm" />
          <q-btn
            flat
            color="deep-purple"
            :label="editingItem ? 'Save' : 'Add Item'"
            :loading="submitting"
            :disable="!itemForm.title"
            @click="submitItem"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Map picker dialog -->
    <q-dialog v-model="showMapDialog" @show="initMap" maximized>
      <q-card class="column" style="max-width: 700px; width: 100%; margin: auto">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Choose Location on Map</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section class="text-caption text-grey-6 q-pt-xs q-pb-none">
          Tap anywhere on the map to drop a pin
        </q-card-section>

        <!-- Map container -->
        <div ref="mapContainer" class="col map-container" />

        <!-- Picked location display -->
        <q-card-section class="q-py-sm" style="min-height: 52px">
          <div v-if="mapLoading" class="row items-center q-gutter-sm text-grey-6">
            <q-spinner size="16px" />
            <span class="text-caption">Fetching location name…</span>
          </div>
          <div v-else-if="mapPickedLocation" class="row items-start q-gutter-xs">
            <q-icon name="gps_fixed" color="deep-purple" size="18px" class="q-mt-xs" />
            <span class="text-body2 col">{{ mapPickedLocation.display_name }}</span>
          </div>
          <div v-else class="text-caption text-grey-5">No pin placed yet</div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            unelevated
            color="deep-purple"
            label="Use This Location"
            :disable="!mapPickedLocation"
            @click="confirmMapLocation"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useQuasar } from 'quasar'
import L from 'leaflet'
import { usePlanningStore } from 'src/stores/planningStore'
import { useTripStore } from 'src/stores/tripStore'

const route = useRoute()
const $q = useQuasar()
const store = usePlanningStore()
const tripStore = useTripStore()
const tripId = route.params.id

const showAddDay = ref(false)
const newDayDate = ref('')
const submitting = ref(false)

const showItemDialog = ref(false)
const editingItem = ref(null)
const activeDayId = ref(null)
const itemForm = ref({ title: '', time: '', notes: '' })

// Location state
const locationSelectRef = ref(null)
const locationSelected = ref(null)
const locationOptions = ref([])
const locationSearching = ref(false)
let locationRawInput = ''

// Map state
const showMapDialog = ref(false)
const mapContainer = ref(null)
const mapPickedLocation = ref(null)
const mapLoading = ref(false)
let mapInstance = null
let mapMarker = null

// SVG pin icon anchored at tip
const PIN_ICON = L.divIcon({
  html: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 32" width="30" height="40">
    <path d="M12 1C7.03 1 3 5.03 3 10c0 7.25 9 21 9 21s9-13.75 9-21c0-4.97-4.03-9-9-9z"
      fill="#673ab7" stroke="white" stroke-width="1.5"/>
    <circle cx="12" cy="10" r="3.5" fill="white"/>
  </svg>`,
  iconSize: [30, 40],
  iconAnchor: [15, 40],
  className: '',
})

// ─── Computed ───────────────────────────────────────────────────────────────

const trip = computed(() => tripStore.currentTrip)

const tripDateOptions = computed(() => {
  const t = trip.value
  if (!t?.start_date || !t?.end_date) return () => true
  const start = t.start_date.replace(/-/g, '/')
  const end = t.end_date.replace(/-/g, '/')
  return (dateStr) => dateStr >= start && dateStr <= end
})

const sortedItinerary = computed(() =>
  [...store.itinerary]
    .sort((a, b) => a.date.localeCompare(b.date))
    .map((day, i) => ({ ...day, day_number: i + 1 }))
)

// ─── Lifecycle ──────────────────────────────────────────────────────────────

onMounted(async () => {
  store.itinerary = []
  await Promise.all([
    store.fetchItinerary(tripId),
    trip.value?.id === parseInt(tripId) ? Promise.resolve() : tripStore.fetchTrip(tripId),
  ])
  store.subscribeToPlanning(tripId)
})

onUnmounted(() => {
  store.unsubscribeFromPlanning(tripId)
  if (mapInstance) {
    mapInstance.remove()
    mapInstance = null
    mapMarker = null
  }
})

// ─── Location search ────────────────────────────────────────────────────────

const searchLocations = async (val, update) => {
  locationRawInput = val
  if (!val || val.length < 2) {
    update(() => { locationOptions.value = [] })
    return
  }
  locationSearching.value = true
  try {
    const url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(val)}&format=json&limit=5&countrycodes=pk`
    const res = await fetch(url, { headers: { Accept: 'application/json' } })
    const data = await res.json()
    update(() => { locationOptions.value = data })
  } catch {
    update(() => { locationOptions.value = [] })
  } finally {
    locationSearching.value = false
  }
}

const clearLocation = () => {
  locationSelected.value = null
  locationRawInput = ''
  locationOptions.value = []
}

// ─── Map picker ─────────────────────────────────────────────────────────────

const openMapDialog = () => {
  // Pre-populate pin from existing selection
  mapPickedLocation.value = locationSelected.value
    ? { ...locationSelected.value }
    : null
  showMapDialog.value = true
}

const initMap = () => {
  if (!mapContainer.value) return

  if (mapInstance) {
    // Re-opening: fix size and restore pin if needed
    mapInstance.invalidateSize()
    if (mapPickedLocation.value && !mapMarker) {
      placePin(parseFloat(mapPickedLocation.value.lat), parseFloat(mapPickedLocation.value.lon))
    }
    return
  }

  mapInstance = L.map(mapContainer.value, {
    center: [30.3753, 69.3451], // Pakistan center
    zoom: 5,
    zoomControl: true,
  })

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(mapInstance)

  // If there's a preselected location, fly to it
  if (mapPickedLocation.value?.lat) {
    const lat = parseFloat(mapPickedLocation.value.lat)
    const lon = parseFloat(mapPickedLocation.value.lon)
    mapInstance.setView([lat, lon], 14)
    placePin(lat, lon)
  }

  mapInstance.on('click', (e) => {
    reverseGeocode(e.latlng.lat, e.latlng.lng)
  })
}

const placePin = (lat, lon) => {
  if (mapMarker) {
    mapMarker.setLatLng([lat, lon])
  } else {
    mapMarker = L.marker([lat, lon], { icon: PIN_ICON }).addTo(mapInstance)
  }
  mapInstance.panTo([lat, lon])
}

const reverseGeocode = async (lat, lon) => {
  placePin(lat, lon)
  mapLoading.value = true
  mapPickedLocation.value = null
  try {
    const url = `https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lon}&format=json`
    const res = await fetch(url, { headers: { Accept: 'application/json' } })
    const data = await res.json()
    mapPickedLocation.value = {
      display_name: data.display_name,
      lat: String(lat),
      lon: String(lon),
    }
  } catch {
    // Fall back to raw coordinates if reverse geocode fails
    mapPickedLocation.value = {
      display_name: `${lat.toFixed(5)}, ${lon.toFixed(5)}`,
      lat: String(lat),
      lon: String(lon),
    }
  } finally {
    mapLoading.value = false
  }
}

const confirmMapLocation = () => {
  if (mapPickedLocation.value) {
    locationSelected.value = { ...mapPickedLocation.value }
    locationRawInput = mapPickedLocation.value.display_name
  }
  showMapDialog.value = false
}

// ─── Day actions ────────────────────────────────────────────────────────────

const submitAddDay = async () => {
  if (!newDayDate.value) return
  submitting.value = true
  try {
    await store.addDay(tripId, newDayDate.value)
    showAddDay.value = false
    newDayDate.value = ''
  } catch (err) {
    $q.notify({ color: 'negative', message: err.response?.data?.message || 'Failed to add day', icon: 'error' })
  } finally {
    submitting.value = false
  }
}

const confirmDeleteDay = (day) => {
  $q.dialog({
    title: 'Delete Day?',
    message: `Delete Day ${day.day_number} (${formatDate(day.date)}) and all its items?`,
    cancel: true,
    persistent: true,
    color: 'negative',
  }).onOk(async () => {
    try {
      await store.deleteDay(tripId, day.id)
    } catch {
      $q.notify({ color: 'negative', message: 'Failed to delete day', icon: 'error' })
    }
  })
}

// ─── Item actions ────────────────────────────────────────────────────────────

const sortedItems = (items) =>
  [...items].sort((a, b) => {
    if (!a.time && !b.time) return 0
    if (!a.time) return 1
    if (!b.time) return -1
    return a.time.localeCompare(b.time)
  })

const resetItemForm = () => {
  itemForm.value = { title: '', time: '', notes: '' }
  locationSelected.value = null
  locationRawInput = ''
  locationOptions.value = []
  // Reset map state so next open starts fresh
  if (mapMarker) {
    mapMarker.remove()
    mapMarker = null
  }
  mapPickedLocation.value = null
}

const openAddItem = (day) => {
  editingItem.value = null
  activeDayId.value = day.id
  resetItemForm()
  showItemDialog.value = true
}

const openEditItem = (item, day) => {
  editingItem.value = item
  activeDayId.value = day.id
  itemForm.value = { title: item.title, time: item.time || '', notes: item.notes || '' }

  if (item.latitude) {
    locationSelected.value = {
      display_name: item.location,
      lat: String(item.latitude),
      lon: String(item.longitude),
    }
  } else {
    locationSelected.value = null
    locationRawInput = item.location || ''
    if (item.location) {
      nextTick(() => locationSelectRef.value?.updateInputValue(item.location, true))
    }
  }

  // Reset map marker so it re-places when dialog opens
  if (mapMarker) {
    mapMarker.remove()
    mapMarker = null
  }
  mapPickedLocation.value = null

  showItemDialog.value = true
}

const submitItem = async () => {
  if (!itemForm.value.title) return
  submitting.value = true
  try {
    const loc = locationSelected.value
    const payload = {
      title:     itemForm.value.title,
      time:      itemForm.value.time || null,
      location:  loc ? loc.display_name : (locationRawInput || null),
      latitude:  loc?.lat ? parseFloat(loc.lat) : null,
      longitude: loc?.lon ? parseFloat(loc.lon) : null,
      notes:     itemForm.value.notes || null,
    }
    if (editingItem.value) {
      await store.updateItineraryItem(tripId, editingItem.value.id, payload)
    } else {
      await store.addItineraryItem(tripId, activeDayId.value, payload)
    }
    showItemDialog.value = false
    resetItemForm()
  } catch (err) {
    $q.notify({ color: 'negative', message: err.response?.data?.message || 'Failed', icon: 'error' })
  } finally {
    submitting.value = false
  }
}

const doDeleteItem = async (itemId) => {
  try {
    await store.deleteItineraryItem(tripId, itemId)
  } catch {
    $q.notify({ color: 'negative', message: 'Failed to delete item', icon: 'error' })
  }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

const formatDate = (dateStr) => {
  if (!dateStr) return ''
  return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-PK', {
    weekday: 'short', day: 'numeric', month: 'short', year: 'numeric',
  })
}
</script>

<style>
/* Leaflet CSS */
@import 'leaflet/dist/leaflet.css';
</style>

<style scoped>
.map-container {
  min-height: 400px;
}
</style>
