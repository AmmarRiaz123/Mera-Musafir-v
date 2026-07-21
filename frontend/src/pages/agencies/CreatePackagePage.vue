<template>
  <q-page class="form-page">
    <div class="form-shell">
      <div class="page-head">
        <q-btn flat round dense color="primary" icon="arrow_back" @click="$router.back()" />
        <div>
          <h1 class="page-title">Create a Package</h1>
          <p class="page-sub">List a departure travellers can book.</p>
        </div>
      </div>

      <q-card flat bordered class="form-card">
        <q-stepper v-model="step" ref="stepper" color="primary" animated flat header-nav>
          <!-- ── Step 1 ─────────────────────────────────────────── -->
          <q-step :name="1" title="Basics" icon="info" :done="step > 1">
            <div class="step-body">
              <section class="form-section">
                <div class="section-head">
                  <q-icon name="edit_note" size="18px" /><span>What are you offering?</span>
                </div>

                <div class="row q-col-gutter-md">
                  <div class="col-12">
                    <q-input
                      v-model="form.title"
                      label="Package title *"
                      placeholder="e.g. Hunza Valley 6-Day Explorer"
                      outlined no-error-icon maxlength="150" counter
                      :rules="[v => !!v || 'Give the package a name']"
                    />
                  </div>

                  <div class="col-12 col-sm-6">
                    <q-select
                      v-model="form.destination_id"
                      :options="destinations"
                      option-value="id" option-label="name" emit-value map-options
                      label="Destination *" outlined no-error-icon
                      :loading="loadingDest"
                      :rules="[v => !!v || 'Pick a destination']"
                    >
                      <template v-slot:prepend><q-icon name="place" color="primary" /></template>
                    </q-select>
                  </div>

                  <div class="col-12 col-sm-6">
                    <q-select
                      v-model="form.type"
                      :options="typeOptions"
                      option-value="value" option-label="label" emit-value map-options
                      label="Package type *" outlined no-error-icon
                      :rules="[v => !!v || 'Pick a type']"
                    />
                  </div>
                </div>
              </section>

              <q-separator class="section-divider" />

              <section class="form-section">
                <div class="section-head">
                  <q-icon name="payments" size="18px" /><span>Price &amp; capacity</span>
                </div>

                <div class="row q-col-gutter-md">
                  <div class="col-12 col-sm-6">
                    <q-input
                      v-model.number="form.price_per_person"
                      type="number" inputmode="numeric" prefix="PKR"
                      label="Price per person *" outlined no-error-icon
                      :rules="[v => v > 0 || 'Must be more than 0']"
                    />
                  </div>
                  <div class="col-12 col-sm-6">
                    <q-input
                      v-model.number="form.max_capacity"
                      type="number" inputmode="numeric"
                      label="Seats available *" outlined no-error-icon
                      input-class="text-center text-weight-medium"
                      class="stepper-input"
                      :rules="[v => v > 0 || 'Must be at least 1']"
                    >
                      <template v-slot:prepend>
                        <q-btn flat dense round icon="remove" size="sm" color="primary"
                          :disable="!(form.max_capacity > 1)" @click.stop="stepSeats(-1)" />
                      </template>
                      <template v-slot:append>
                        <q-btn flat dense round icon="add" size="sm" color="primary"
                          @click.stop="stepSeats(1)" />
                      </template>
                    </q-input>
                  </div>
                </div>
                <p class="field-note q-mt-sm">
                  One booking can hold several seats — a family of four fills four.
                </p>
              </section>
            </div>
          </q-step>

          <!-- ── Step 2 ─────────────────────────────────────────── -->
          <q-step :name="2" title="Dates &amp; Details" icon="date_range" :done="step > 2">
            <div class="step-body">
              <section class="form-section">
                <div class="section-head">
                  <q-icon name="event" size="18px" /><span>When does it run?</span>
                </div>

                <div class="row q-col-gutter-md">
                  <div class="col-12 col-sm-6">
                    <div class="date-field">
                      <q-input
                        v-model="form.start_date" label="Departure *" placeholder="Select a date"
                        outlined readonly no-error-icon class="cursor-pointer"
                        :rules="[v => !!v || 'Pick a departure date']"
                      >
                        <template v-slot:prepend>
                          <q-icon name="calendar_today" color="primary" size="18px" />
                        </template>
                      </q-input>
                      <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                        <q-date v-model="form.start_date" mask="YYYY-MM-DD" :options="futureDates" minimal>
                          <div class="row items-center justify-end">
                            <q-btn v-close-popup label="Done" color="primary" flat no-caps />
                          </div>
                        </q-date>
                      </q-popup-proxy>
                    </div>
                  </div>

                  <div class="col-12 col-sm-6">
                    <div class="date-field">
                      <q-input
                        v-model="form.end_date" label="Return *" placeholder="Select a date"
                        outlined readonly no-error-icon class="cursor-pointer"
                        :rules="[
                          v => !!v || 'Pick a return date',
                          v => !form.start_date || v >= form.start_date || 'Must be after departure'
                        ]"
                      >
                        <template v-slot:prepend>
                          <q-icon name="calendar_today" color="primary" size="18px" />
                        </template>
                      </q-input>
                      <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                        <q-date v-model="form.end_date" mask="YYYY-MM-DD" :options="afterStart" minimal>
                          <div class="row items-center justify-end">
                            <q-btn v-close-popup label="Done" color="primary" flat no-caps />
                          </div>
                        </q-date>
                      </q-popup-proxy>
                    </div>
                  </div>
                </div>

                <transition name="fade-slide">
                  <div v-if="durationDays > 0" class="duration-pill">
                    <q-icon name="schedule" size="16px" />
                    {{ durationDays }} day{{ durationDays !== 1 ? 's' : '' }}
                  </div>
                </transition>
              </section>

              <q-separator class="section-divider" />

              <section class="form-section">
                <div class="section-head">
                  <q-icon name="description" size="18px" /><span>Describe the experience</span>
                </div>

                <q-input
                  v-model="form.description"
                  label="Description *"
                  placeholder="What's the itinerary, who is it for, what should they expect?"
                  outlined no-error-icon type="textarea" rows="4" maxlength="2000"
                  :rules="[v => !!v || 'Add a description']"
                />

                <q-select
                  v-model="form.includes"
                  :options="includesOptions"
                  multiple use-chips use-input new-value-mode="add-unique"
                  label="What's included" outlined no-error-icon class="q-mt-md"
                  hint="Pick from the list, or type your own and press Enter"
                />
              </section>

              <q-separator class="section-divider" />

              <section class="form-section">
                <div class="section-head">
                  <q-icon name="image" size="18px" />
                  <span>Cover photo</span>
                  <span class="optional-tag">Optional</span>
                </div>
                <ImageUpload
                  v-model="form.cover_image"
                  type="package_cover"
                  label="Add a cover photo"
                  class="full-width"
                />
                <p class="field-note q-mt-sm">
                  Packages with a photo get noticeably more bookings.
                </p>
              </section>
            </div>
          </q-step>

          <!-- ── Step 3 ─────────────────────────────────────────── -->
          <q-step :name="3" title="Review" icon="rocket_launch">
            <div class="step-body">
              <div class="review-card">
                <div
                  class="review-cover"
                  :style="coverStyle"
                >
                  <span v-if="!form.cover_image" class="review-cover-empty">
                    <q-icon name="landscape" size="26px" />No cover photo
                  </span>
                </div>

                <div class="review-body">
                  <div class="review-title">{{ form.title || 'Untitled package' }}</div>
                  <div class="review-grid">
                    <div v-for="row in reviewRows" :key="row.label" class="review-item">
                      <span class="review-label">{{ row.label }}</span>
                      <span class="review-value">{{ row.value }}</span>
                    </div>
                  </div>

                  <div v-if="form.includes?.length" class="q-mt-md">
                    <span class="review-label">Includes</span>
                    <div class="row q-gutter-xs q-mt-xs">
                      <q-chip
                        v-for="item in form.includes" :key="item"
                        size="sm" color="deep-purple-1" text-color="deep-purple-9"
                      >{{ item }}</q-chip>
                    </div>
                  </div>
                </div>
              </div>

              <div class="publish-row">
                <q-btn
                  outline no-caps color="grey-7" label="Save as draft"
                  :loading="submitting" @click="submit('draft')"
                />
                <q-btn
                  unelevated rounded no-caps color="primary"
                  icon="rocket_launch" label="Publish package"
                  :loading="submitting" @click="submit('published')"
                />
              </div>
            </div>
          </q-step>

          <template v-slot:navigation>
            <div class="wizard-nav">
              <q-btn
                v-if="step > 1"
                flat no-caps color="primary" label="Back" icon="arrow_back"
                @click="$refs.stepper.previous()"
              />
              <div v-else />
              <div class="nav-right">
                <span class="step-count">Step {{ step }} of 3</span>
                <q-btn
                  v-if="step < 3"
                  unelevated rounded no-caps color="primary"
                  label="Continue" icon-right="arrow_forward"
                  :disable="!canProceed"
                  @click="$refs.stepper.next()"
                />
              </div>
            </div>
          </template>
        </q-stepper>
      </q-card>
    </div>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAgencyStore } from 'src/stores/agencyStore'
import { api } from 'src/boot/axios'
import ImageUpload from 'components/ImageUpload.vue'

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
  cover_image: null,
})

const typeOptions = [
  { label: 'Day trip', value: 'day_trip' },
  { label: 'Weekend', value: 'weekend' },
  { label: 'Extended', value: 'extended' },
  { label: 'Custom', value: 'custom' },
]

const includesOptions = [
  'Meals', 'Breakfast', 'Transport', 'Accommodation', 'Guide',
  'Equipment', 'Permits', 'Insurance', 'Photography', 'Camping Gear',
]

const today = new Date().toISOString().slice(0, 10).replace(/-/g, '/')
const futureDates = (d) => d >= today
const afterStart = (d) => (form.start_date ? d >= form.start_date.replace(/-/g, '/') : d >= today)

const stepSeats = (delta) => {
  form.max_capacity = Math.max(1, (Number(form.max_capacity) || 0) + delta)
}

const durationDays = computed(() => {
  if (!form.start_date || !form.end_date) return 0
  const diff = new Date(form.end_date) - new Date(form.start_date)
  return Math.max(0, Math.floor(diff / 86400000) + 1)
})

const destinationName = computed(
  () => destinations.value.find((d) => d.id === form.destination_id)?.name ?? '—',
)

// The uploader stores a path; show the served URL in the review preview.
const coverStyle = computed(() => {
  if (!form.cover_image) return {}
  const url = /^(https?:|data:)/.test(form.cover_image)
    ? form.cover_image
    : `http://localhost:8000/storage/${form.cover_image}`
  return { backgroundImage: `url(${url})` }
})

const reviewRows = computed(() => [
  { label: 'Destination', value: destinationName.value },
  { label: 'Type',        value: typeOptions.find((t) => t.value === form.type)?.label ?? '—' },
  { label: 'Price',       value: form.price_per_person ? `PKR ${Number(form.price_per_person).toLocaleString()}` : '—' },
  { label: 'Seats',       value: form.max_capacity ? `${form.max_capacity} available` : '—' },
  {
    label: 'Dates',
    value: form.start_date && form.end_date
      ? `${form.start_date} → ${form.end_date} (${durationDays.value}d)`
      : '—',
  },
])

const canProceed = computed(() => {
  if (step.value === 1) {
    return form.title && form.destination_id && form.type && form.price_per_person > 0 && form.max_capacity > 0
  }
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
    await store.createPackage({
      ...form,
      status,
      includes: form.includes.length ? form.includes : null,
      cover_image: form.cover_image || null,
    })
    $q.notify({
      color: 'positive',
      icon: 'check_circle',
      position: 'top',
      message: `Package ${status === 'published' ? 'published' : 'saved as draft'}`,
    })
    router.push(`/agencies/${store.myAgency?.slug}/dashboard?section=packages`)
  } catch (err) {
    $q.notify({
      color: 'negative',
      icon: 'error',
      position: 'top',
      message: err.response?.data?.message || 'Failed to create package',
    })
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.form-page { background: #faf8fc; padding: 24px 16px 64px; }
.form-shell { max-width: 760px; margin: 0 auto; }

.page-head { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 20px; }
.page-title { margin: 0; font-size: 30px; line-height: 1.15; font-weight: 700; letter-spacing: -0.02em; color: #2b1b33; }
.page-sub { margin: 4px 0 0; font-size: 14px; color: #7a6a82; }

.form-card {
  border-radius: 16px;
  border-color: #ece6f0;
  background: #fff;
  box-shadow: 0 1px 2px rgba(43,27,51,0.04), 0 8px 24px rgba(43,27,51,0.06);
  overflow: hidden;
}
.step-body { padding-top: 4px; }
.form-section + .form-section { margin-top: 4px; }

.section-head {
  display: flex; align-items: center; gap: 8px; margin-bottom: 16px;
  font-size: 15px; font-weight: 600; color: #2b1b33;
}
.section-head .q-icon { color: var(--q-primary); }

.optional-tag {
  margin-left: 2px; padding: 2px 8px; border-radius: 999px;
  background: #f1ecf5; color: #7a6a82; font-size: 11px; font-weight: 500;
}
.section-divider { margin: 28px 0; background: #f0eaf4; }
.field-note { margin: 0; font-size: 12.5px; line-height: 1.45; color: #7a6a82; }

/* Dates */
.date-field { position: relative; }
.date-field :deep(.q-field--readonly .q-field__control:before) { border-style: solid; }
.date-field :deep(.q-field--readonly .q-field__native) { cursor: pointer; }

.duration-pill {
  display: inline-flex; align-items: center; gap: 6px; margin-top: 4px;
  padding: 6px 12px; border-radius: 999px;
  background: #f3e9f8; color: #6a3d7d; font-size: 12.5px; font-weight: 500;
}
.fade-slide-enter-active, .fade-slide-leave-active { transition: opacity .2s ease, transform .2s ease; }
.fade-slide-enter-from, .fade-slide-leave-to { opacity: 0; transform: translateY(-4px); }

/* Kill native number spinners */
.step-body :deep(input[type='number'])::-webkit-outer-spin-button,
.step-body :deep(input[type='number'])::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.step-body :deep(input[type='number']) { -moz-appearance: textfield; }
.stepper-input :deep(.q-field__prepend),
.stepper-input :deep(.q-field__append) { padding: 0 4px; }

/* Review */
.review-card { border: 1px solid #ece6f0; border-radius: 13px; overflow: hidden; background: #fff; }
.review-cover {
  height: 150px;
  background: linear-gradient(135deg, #7b1fa2, #4a148c) center/cover no-repeat;
  display: flex; align-items: center; justify-content: center;
}
.review-cover-empty {
  display: flex; align-items: center; gap: 8px;
  color: rgba(255,255,255,0.75); font-size: 12.5px;
}
.review-body { padding: 16px 18px; }
.review-title { font-size: 17px; font-weight: 700; color: #2b1b33; margin-bottom: 14px; }
.review-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px 18px; }
.review-item { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.review-label {
  font-size: 10.5px; font-weight: 700; letter-spacing: 0.06em;
  text-transform: uppercase; color: #9b8aa5;
}
.review-value { font-size: 13.5px; color: #2b1b33; overflow-wrap: anywhere; }

.publish-row { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; flex-wrap: wrap; }

/* Navigation */
.wizard-nav {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  margin-top: 8px; padding-top: 20px; border-top: 1px solid #f0eaf4;
}
.nav-right { display: flex; align-items: center; gap: 14px; }
.step-count { font-size: 12.5px; color: #9b8aa5; }

@media (max-width: 599px) {
  .form-page { padding: 16px 12px 48px; }
  .page-title { font-size: 24px; }
  .step-count { display: none; }
}
</style>
