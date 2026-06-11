<template>
  <q-page padding>
    <div class="row items-center q-mb-lg">
      <q-btn flat round dense icon="arrow_back" @click="$router.back()" />
      <div class="text-h5 text-weight-bold q-ml-sm">Create Package</div>
    </div>

    <div class="row justify-center">
      <div class="col-12" style="max-width: 700px">
        <q-stepper
          v-model="step"
          ref="stepper"
          color="deep-purple"
          animated
          flat
          bordered
        >
          <!-- Step 1: Basic info -->
          <q-step :name="1" title="Basic Info" icon="info" :done="step > 1">
            <div class="column q-gutter-md q-mt-sm">
              <q-input
                v-model="form.title"
                label="Package Title *"
                outlined dense
                :rules="[v => !!v || 'Required']"
              />

              <q-select
                v-model="form.destination_id"
                :options="destinations"
                option-value="id"
                option-label="name"
                emit-value
                map-options
                label="Destination *"
                outlined dense
                :loading="loadingDest"
                :rules="[v => !!v || 'Required']"
              />

              <q-select
                v-model="form.type"
                :options="typeOptions"
                option-value="value"
                option-label="label"
                emit-value
                map-options
                label="Package Type *"
                outlined dense
                :rules="[v => !!v || 'Required']"
              />

              <div class="row q-gutter-md">
                <div class="col">
                  <q-input
                    v-model.number="form.price_per_person"
                    type="number"
                    label="Price per Person (PKR) *"
                    outlined dense
                    min="1"
                    :rules="[v => v > 0 || 'Must be greater than 0']"
                    prefix="PKR"
                  />
                </div>
                <div class="col">
                  <q-input
                    v-model.number="form.max_capacity"
                    type="number"
                    label="Max Capacity *"
                    outlined dense
                    min="1"
                    :rules="[v => v > 0 || 'Must be greater than 0']"
                    suffix="travelers"
                  />
                </div>
              </div>
            </div>
          </q-step>

          <!-- Step 2: Dates & Details -->
          <q-step :name="2" title="Dates & Details" icon="calendar_today" :done="step > 2">
            <div class="column q-gutter-md q-mt-sm">
              <div class="row q-gutter-md">
                <div class="col">
                  <q-input v-model="form.start_date" label="Start Date *" outlined dense>
                    <template v-slot:append>
                      <q-icon name="event" class="cursor-pointer">
                        <q-popup-proxy>
                          <q-date v-model="form.start_date" mask="YYYY-MM-DD" minimal>
                            <div class="row items-center justify-end">
                              <q-btn v-close-popup label="Close" flat color="primary" />
                            </div>
                          </q-date>
                        </q-popup-proxy>
                      </q-icon>
                    </template>
                  </q-input>
                </div>
                <div class="col">
                  <q-input v-model="form.end_date" label="End Date *" outlined dense>
                    <template v-slot:append>
                      <q-icon name="event" class="cursor-pointer">
                        <q-popup-proxy>
                          <q-date v-model="form.end_date" mask="YYYY-MM-DD" minimal>
                            <div class="row items-center justify-end">
                              <q-btn v-close-popup label="Close" flat color="primary" />
                            </div>
                          </q-date>
                        </q-popup-proxy>
                      </q-icon>
                    </template>
                  </q-input>
                </div>
              </div>

              <div v-if="durationDays > 0" class="text-caption text-deep-purple">
                <q-icon name="schedule" size="xs" class="q-mr-xs" />
                Duration: {{ durationDays }} day{{ durationDays !== 1 ? 's' : '' }}
              </div>

              <q-input
                v-model="form.description"
                label="Description *"
                outlined dense
                type="textarea"
                :rows="4"
                :rules="[v => !!v || 'Required']"
              />

              <q-select
                v-model="form.includes"
                :options="includesOptions"
                multiple
                use-chips
                use-input
                new-value-mode="add-unique"
                label="What's Included"
                outlined dense
                hint="Select from list or type and press Enter to add custom items"
              />

              <q-input
                v-model="form.cover_image"
                label="Cover Image URL (optional)"
                outlined dense
                placeholder="https://..."
              />
            </div>
          </q-step>

          <!-- Step 3: Review -->
          <q-step :name="3" title="Review & Publish" icon="rocket_launch">
            <div class="column q-gutter-md q-mt-sm">
              <q-card flat bordered>
                <q-card-section>
                  <div class="text-subtitle1 text-weight-bold q-mb-sm">Package Summary</div>
                  <q-list dense>
                    <q-item v-for="row in reviewRows" :key="row.label">
                      <q-item-section class="text-grey-7 text-caption" style="max-width: 140px">{{ row.label }}</q-item-section>
                      <q-item-section class="text-weight-medium text-body2">{{ row.value }}</q-item-section>
                    </q-item>
                  </q-list>
                </q-card-section>
              </q-card>

              <div v-if="form.includes?.length">
                <div class="text-caption text-grey-6 q-mb-xs">Includes:</div>
                <div class="row q-gutter-xs">
                  <q-chip v-for="item in form.includes" :key="item" size="sm" color="deep-purple-1" text-color="deep-purple-9">
                    {{ item }}
                  </q-chip>
                </div>
              </div>

              <div class="row q-gutter-md">
                <q-btn
                  outline color="grey-7"
                  label="Save as Draft"
                  :loading="submitting"
                  @click="submit('draft')"
                />
                <q-btn
                  unelevated color="deep-purple"
                  label="Publish Package"
                  :loading="submitting"
                  @click="submit('published')"
                />
              </div>
            </div>
          </q-step>

          <!-- Navigation -->
          <template v-slot:navigation>
            <q-stepper-navigation>
              <q-btn
                v-if="step < 3"
                unelevated
                color="deep-purple"
                :label="step < 3 ? 'Continue' : ''"
                :disable="!canProceed"
                @click="$refs.stepper.next()"
              />
              <q-btn
                v-if="step > 1"
                flat
                color="grey-7"
                label="Back"
                class="q-ml-sm"
                @click="$refs.stepper.previous()"
              />
            </q-stepper-navigation>
          </template>
        </q-stepper>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAgencyStore } from 'src/stores/agencyStore'
import { api } from 'src/boot/axios'

const router = useRouter()
const $q = useQuasar()
const store = useAgencyStore()

const step = ref(1)
const submitting = ref(false)
const destinations = ref([])
const loadingDest = ref(false)

const form = reactive({
  title: '',
  destination_id: null,
  type: null,
  price_per_person: null,
  max_capacity: null,
  start_date: '',
  end_date: '',
  description: '',
  includes: [],
  cover_image: '',
})

const typeOptions = [
  { label: 'Day Trip', value: 'day_trip' },
  { label: 'Weekend', value: 'weekend' },
  { label: 'Extended', value: 'extended' },
  { label: 'Custom', value: 'custom' },
]

const includesOptions = [
  'Meals', 'Breakfast', 'Transport', 'Accommodation', 'Guide',
  'Equipment', 'Permits', 'Insurance', 'Photography', 'Camping Gear',
]

const durationDays = computed(() => {
  if (!form.start_date || !form.end_date) return 0
  const diff = new Date(form.end_date) - new Date(form.start_date)
  return Math.max(0, Math.floor(diff / 86400000) + 1)
})

const destinationName = computed(() =>
  destinations.value.find((d) => d.id === form.destination_id)?.name ?? '—'
)

const reviewRows = computed(() => [
  { label: 'Title',       value: form.title || '—' },
  { label: 'Destination', value: destinationName.value },
  { label: 'Type',        value: typeOptions.find((t) => t.value === form.type)?.label ?? '—' },
  { label: 'Price',       value: form.price_per_person ? `PKR ${Number(form.price_per_person).toLocaleString()}` : '—' },
  { label: 'Capacity',    value: form.max_capacity ? `${form.max_capacity} travelers` : '—' },
  { label: 'Dates',       value: form.start_date && form.end_date ? `${form.start_date} → ${form.end_date} (${durationDays.value}D)` : '—' },
])

const canProceed = computed(() => {
  if (step.value === 1) return form.title && form.destination_id && form.type && form.price_per_person > 0 && form.max_capacity > 0
  if (step.value === 2) return form.start_date && form.end_date && form.description
  return true
})

onMounted(async () => {
  loadingDest.value = true
  try {
    const r = await api.get('/api/v1/destinations', { params: { per_page: 100 } })
    destinations.value = r.data.data
  } finally {
    loadingDest.value = false
  }
})

const submit = async (status) => {
  submitting.value = true
  try {
    const payload = {
      ...form,
      status,
      includes: form.includes.length ? form.includes : null,
      cover_image: form.cover_image || null,
    }
    await store.createPackage(payload)
    $q.notify({ color: 'positive', message: `Package ${status === 'published' ? 'published' : 'saved as draft'}`, icon: 'check' })
    router.push(`/agencies/${store.myAgency?.slug}/dashboard`)
  } catch (err) {
    $q.notify({ color: 'negative', message: err.response?.data?.message || 'Failed to create package', icon: 'error' })
  } finally {
    submitting.value = false
  }
}
</script>
