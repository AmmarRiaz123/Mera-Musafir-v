<template>
  <q-page padding>
    <div class="row items-center q-mb-lg">
      <q-btn flat color="primary" icon="arrow_back" @click="$router.push('/trips')" class="q-mr-sm" />
      <div class="text-h4 text-weight-bold">Create a Trip</div>
    </div>

    <div class="row justify-center">
      <div class="col-12 col-md-8 col-lg-7">
        <q-stepper
          v-model="step"
          color="primary"
          animated
          flat
          bordered
        >
          <!-- Step 1: Basic Info -->
          <q-step :name="1" title="Basic Info" icon="info" :done="step > 1">
            <div class="q-gutter-md q-mt-sm">
              <q-input
                v-model="form.title"
                label="Trip Title *"
                outlined
                :rules="[val => !!val || 'Title is required', val => val.length <= 150 || 'Max 150 characters']"
                hint="Give your trip a catchy name"
              />

              <q-select
                v-model="selectedDestination"
                label="Destination *"
                outlined
                :options="destinationOptions"
                option-value="id"
                option-label="name"
                emit-value
                map-options
                use-input
                input-debounce="300"
                @filter="filterDestinations"
                :rules="[val => !!val || 'Destination is required']"
              >
                <template v-slot:no-option>
                  <q-item>
                    <q-item-section class="text-grey">No destinations found</q-item-section>
                  </q-item>
                </template>
              </q-select>

              <q-input
                v-model="form.description"
                label="Description"
                outlined
                type="textarea"
                rows="4"
                :rules="[val => !val || val.length <= 2000 || 'Max 2000 characters']"
                hint="Tell people what this trip is about (optional)"
              />
            </div>
          </q-step>

          <!-- Step 2: Dates & Capacity -->
          <q-step :name="2" title="Dates & Budget" icon="date_range" :done="step > 2">
            <div class="q-gutter-md q-mt-sm">
              <div class="row q-col-gutter-md">
                <div class="col-12 col-sm-6">
                  <q-input
                    v-model="form.start_date"
                    label="Start Date *"
                    outlined
                    :rules="[val => !!val || 'Start date is required']"
                  >
                    <template v-slot:append>
                      <q-icon name="event" class="cursor-pointer">
                        <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                          <q-date v-model="form.start_date" mask="YYYY-MM-DD" :options="futureDates">
                            <div class="row items-center justify-end">
                              <q-btn v-close-popup label="Close" color="primary" flat />
                            </div>
                          </q-date>
                        </q-popup-proxy>
                      </q-icon>
                    </template>
                  </q-input>
                </div>
                <div class="col-12 col-sm-6">
                  <q-input
                    v-model="form.end_date"
                    label="End Date *"
                    outlined
                    :rules="[
                      val => !!val || 'End date is required',
                      val => !form.start_date || val >= form.start_date || 'End date must be after start date'
                    ]"
                  >
                    <template v-slot:append>
                      <q-icon name="event" class="cursor-pointer">
                        <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                          <q-date v-model="form.end_date" mask="YYYY-MM-DD" :options="afterStartDate">
                            <div class="row items-center justify-end">
                              <q-btn v-close-popup label="Close" color="primary" flat />
                            </div>
                          </q-date>
                        </q-popup-proxy>
                      </q-icon>
                    </template>
                  </q-input>
                </div>
              </div>

              <q-input
                v-model.number="form.max_travelers"
                label="Max Travelers *"
                outlined
                type="number"
                :rules="[
                  val => !!val || 'Required',
                  val => val >= 2 || 'Minimum 2 travelers',
                  val => val <= 100 || 'Maximum 100 travelers'
                ]"
                hint="Including yourself (min 2, max 100)"
              />

              <div class="row q-col-gutter-md">
                <div class="col-12 col-sm-6">
                  <q-input
                    v-model.number="form.budget_min"
                    label="Min Budget (PKR)"
                    outlined
                    type="number"
                    :rules="[val => !val || val >= 0 || 'Must be positive']"
                    hint="Optional"
                  />
                </div>
                <div class="col-12 col-sm-6">
                  <q-input
                    v-model.number="form.budget_max"
                    label="Max Budget (PKR)"
                    outlined
                    type="number"
                    :rules="[
                      val => !val || val >= 0 || 'Must be positive',
                      val => !val || !form.budget_min || val >= form.budget_min || 'Max must be ≥ min'
                    ]"
                    hint="Optional"
                  />
                </div>
              </div>
            </div>
          </q-step>

          <!-- Step 3: Type & Visibility -->
          <q-step :name="3" title="Character" icon="settings">
            <div class="q-gutter-md q-mt-sm">
              <q-select
                v-model="form.type"
                label="Trip Type *"
                outlined
                :options="typeOptions"
                :rules="[val => !!val || 'Trip type is required']"
              />

              <div>
                <div class="text-subtitle2 text-grey-8 q-mb-sm">Visibility *</div>
                <div class="q-gutter-sm">
                  <q-btn-toggle
                    v-model="form.visibility"
                    spread
                    no-caps
                    unelevated
                    toggle-color="primary"
                    color="white"
                    text-color="grey-8"
                    :options="visibilityOptions"
                  />
                </div>
                <div class="text-caption text-grey-6 q-mt-sm">
                  <span v-if="form.visibility === 'public'">Anyone can find and join your trip.</span>
                  <span v-else-if="form.visibility === 'women_only'">Only women can join. Your trip will be marked with a Women Only badge.</span>
                  <span v-else-if="form.visibility === 'invite_only'">People must request to join. You approve each member.</span>
                </div>
              </div>

              <!-- Summary card -->
              <q-card flat bordered class="bg-grey-1 q-mt-lg">
                <q-card-section>
                  <div class="text-subtitle2 q-mb-sm">Trip Summary</div>
                  <div class="row q-col-gutter-sm text-caption text-grey-8">
                    <div class="col-6"><strong>Title:</strong> {{ form.title || '—' }}</div>
                    <div class="col-6"><strong>Destination:</strong> {{ destinationName }}</div>
                    <div class="col-6"><strong>Dates:</strong> {{ form.start_date }} to {{ form.end_date }}</div>
                    <div class="col-6"><strong>Travelers:</strong> {{ form.max_travelers || '—' }}</div>
                    <div class="col-6"><strong>Type:</strong> {{ form.type || '—' }}</div>
                    <div class="col-6"><strong>Visibility:</strong> {{ form.visibility }}</div>
                  </div>
                </q-card-section>
              </q-card>
            </div>
          </q-step>

          <template v-slot:navigation>
            <q-stepper-navigation class="row justify-between q-mt-md">
              <q-btn
                flat
                color="primary"
                label="Back"
                icon="arrow_back"
                @click="step--"
                v-if="step > 1"
              />
              <div v-else />
              <div>
                <q-btn
                  v-if="step < 3"
                  color="primary"
                  label="Continue"
                  icon-right="arrow_forward"
                  @click="nextStep"
                  unelevated
                  rounded
                />
                <q-btn
                  v-else
                  color="positive"
                  label="Create Trip"
                  icon="rocket_launch"
                  @click="submitForm"
                  unelevated
                  rounded
                  :loading="loading"
                />
              </div>
            </q-stepper-navigation>
          </template>
        </q-stepper>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { api } from 'src/boot/axios'
import { useTripStore } from 'src/stores/tripStore'

const router = useRouter()
const $q = useQuasar()
const tripStore = useTripStore()

const step = ref(1)
const loading = ref(false)
const destinationOptions = ref([])
const selectedDestination = ref(null)

const form = ref({
  title: '',
  description: '',
  start_date: '',
  end_date: '',
  max_travelers: null,
  budget_min: null,
  budget_max: null,
  type: '',
  visibility: 'public'
})

const typeOptions = ['adventure', 'cultural', 'budget', 'luxury', 'backpacking']

const visibilityOptions = [
  { label: 'Public', value: 'public' },
  { label: 'Women Only', value: 'women_only' },
  { label: 'Invite Only', value: 'invite_only' }
]

const destinationName = computed(() => {
  if (!selectedDestination.value) return '—'
  const dest = destinationOptions.value.find(d => d.id === selectedDestination.value)
  return dest ? dest.name : '—'
})

const today = new Date().toISOString().slice(0, 10).replace(/-/g, '/')

const futureDates = (date) => date >= today.replace(/-/g, '/')

const afterStartDate = (date) => {
  if (!form.value.start_date) return date >= today.replace(/-/g, '/')
  return date >= form.value.start_date.replace(/-/g, '/')
}

onMounted(async () => {
  const response = await api.get('/api/v1/destinations', { params: { per_page: 50 } })
  destinationOptions.value = response.data.data || []
})

const filterDestinations = (val, update) => {
  api.get('/api/v1/destinations', { params: { search: val } })
    .then(response => {
      update(() => {
        destinationOptions.value = response.data.data || []
      })
    })
    .catch(() => update(() => {}))
}

const nextStep = () => {
  if (step.value === 1) {
    if (!form.value.title || !selectedDestination.value) {
      $q.notify({ color: 'warning', message: 'Please fill in Title and Destination', icon: 'warning' })
      return
    }
  }
  if (step.value === 2) {
    if (!form.value.start_date || !form.value.end_date || !form.value.max_travelers) {
      $q.notify({ color: 'warning', message: 'Please fill in all required date and capacity fields', icon: 'warning' })
      return
    }
    if (form.value.end_date < form.value.start_date) {
      $q.notify({ color: 'warning', message: 'End date must be after start date', icon: 'warning' })
      return
    }
  }
  step.value++
}

const submitForm = async () => {
  if (!form.value.type || !form.value.visibility) {
    $q.notify({ color: 'warning', message: 'Please select trip type and visibility', icon: 'warning' })
    return
  }

  loading.value = true
  try {
    const payload = {
      ...form.value,
      destination_id: selectedDestination.value,
      budget_min: form.value.budget_min || undefined,
      budget_max: form.value.budget_max || undefined,
      description: form.value.description || undefined
    }

    const result = await tripStore.createTrip(payload)
    $q.notify({ color: 'positive', message: 'Trip created successfully!', icon: 'check_circle' })
    router.push(`/trips/${result.data.id}`)
  } catch (err) {
    const errors = err.response?.data?.errors
    if (errors) {
      const firstError = Object.values(errors)[0]?.[0]
      $q.notify({ color: 'negative', message: firstError || 'Validation failed', icon: 'error' })
    } else {
      $q.notify({ color: 'negative', message: err.response?.data?.message || 'Failed to create trip', icon: 'error' })
    }
  } finally {
    loading.value = false
  }
}
</script>
