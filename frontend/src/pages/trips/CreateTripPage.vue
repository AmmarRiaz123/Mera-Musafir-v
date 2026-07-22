<template>
  <q-page class="create-trip-page">
    <div class="wizard-shell">
      <!-- Page header -->
      <div class="page-head">
        <q-btn flat round dense color="primary" icon="arrow_back" @click="$router.push('/trips')" />
        <div>
          <h1 class="page-title">Create a Trip</h1>
          <p class="page-sub">Plan it, share it, find your travel crew.</p>
        </div>
      </div>

      <q-card flat bordered class="wizard-card">
        <q-stepper v-model="step" color="primary" animated flat header-nav class="wizard-stepper">
          <!-- ── Step 1: Basic Info ─────────────────────────────── -->
          <q-step :name="1" title="Basic Info" icon="info" :done="step > 1">
            <div class="step-body">
              <section class="form-section">
                <div class="section-head">
                  <q-icon name="edit_note" size="18px" />
                  <span>What's the trip?</span>
                </div>

                <div class="row q-col-gutter-md">
                  <div class="col-12">
                    <q-input
                      v-model="form.title"
                      label="Trip title *"
                      placeholder="e.g. Hunza Valley in Autumn"
                      outlined
                      no-error-icon
                      counter
                      maxlength="150"
                      :rules="[val => !!val || 'Give your trip a name']"
                    />
                  </div>

                  <div class="col-12">
                    <q-select
                      v-model="selectedDestination"
                      label="Destination *"
                      outlined
                      no-error-icon
                      :options="destinationOptions"
                      option-value="id"
                      option-label="name"
                      emit-value
                      map-options
                      use-input
                      input-debounce="300"
                      @filter="filterDestinations"
                      :rules="[val => !!val || 'Pick a destination']"
                    >
                      <template v-slot:prepend>
                        <q-icon name="place" color="primary" />
                      </template>
                      <template v-slot:no-option>
                        <q-item>
                          <q-item-section class="text-grey">No destinations found</q-item-section>
                        </q-item>
                      </template>
                    </q-select>
                  </div>

                  <div class="col-12">
                    <q-input
                      v-model="form.description"
                      label="Description"
                      placeholder="Who is this trip for? What's the plan?"
                      outlined
                      no-error-icon
                      type="textarea"
                      rows="4"
                      maxlength="2000"
                      hint="Optional — but trips with a description get more joiners."
                    />
                  </div>

                  <div class="col-12">
                    <div class="section-head q-mt-sm">
                      <q-icon name="image" size="18px" />
                      <span>Cover photo</span>
                      <span class="optional-tag">Optional</span>
                    </div>
                    <ImageUpload
                      v-model="form.cover_image"
                      type="trip_cover"
                      label="Add a cover photo"
                      class="full-width"
                    />
                  </div>
                </div>
              </section>
            </div>
          </q-step>

          <!-- ── Step 2: Dates & Budget ─────────────────────────── -->
          <q-step :name="2" title="Dates & Budget" icon="date_range" :done="step > 2">
            <div class="step-body">
              <!-- Dates -->
              <section class="form-section">
                <div class="section-head">
                  <q-icon name="event" size="18px" />
                  <span>When are you travelling?</span>
                </div>

                <div class="row q-col-gutter-md">
                  <div class="col-12 col-sm-6">
                    <div class="date-field">
                      <q-input
                        v-model="form.start_date"
                        label="Start date *"
                        placeholder="Select a date"
                        outlined
                        readonly
                        no-error-icon
                        class="cursor-pointer"
                        :rules="[val => !!val || 'Pick a start date']"
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
                        v-model="form.end_date"
                        label="End date *"
                        placeholder="Select a date"
                        outlined
                        readonly
                        no-error-icon
                        class="cursor-pointer"
                        :rules="[
                          val => !!val || 'Pick an end date',
                          val => !form.start_date || val >= form.start_date || 'Must be after the start date'
                        ]"
                      >
                        <template v-slot:prepend>
                          <q-icon name="calendar_today" color="primary" size="18px" />
                        </template>
                      </q-input>
                      <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                        <q-date v-model="form.end_date" mask="YYYY-MM-DD" :options="afterStartDate" minimal>
                          <div class="row items-center justify-end">
                            <q-btn v-close-popup label="Done" color="primary" flat no-caps />
                          </div>
                        </q-date>
                      </q-popup-proxy>
                    </div>
                  </div>
                </div>

                <transition name="fade-slide">
                  <div v-if="nights > 0" class="duration-pill">
                    <q-icon name="schedule" size="16px" />
                    {{ nights }} {{ nights === 1 ? 'night' : 'nights' }} away
                  </div>
                </transition>
              </section>

              <q-separator class="section-divider" />

              <!-- Capacity -->
              <section class="form-section">
                <div class="section-head">
                  <q-icon name="group" size="18px" />
                  <span>How many people?</span>
                </div>

                <div class="row items-center q-col-gutter-md">
                  <div class="col-12 col-sm-6">
                    <q-input
                      v-model.number="form.max_travelers"
                      label="Max travelers *"
                      outlined
                      no-error-icon
                      type="number"
                      inputmode="numeric"
                      input-class="text-center text-weight-medium"
                      class="stepper-input"
                      :rules="[
                        val => !!val || 'Required',
                        val => val >= 2 || 'At least 2 travelers',
                        val => val <= 100 || 'No more than 100'
                      ]"
                    >
                      <template v-slot:prepend>
                        <q-btn
                          flat dense round icon="remove" size="sm" color="primary"
                          :disable="!canDecrement"
                          @click.stop="stepTravelers(-1)"
                        />
                      </template>
                      <template v-slot:append>
                        <q-btn
                          flat dense round icon="add" size="sm" color="primary"
                          :disable="!canIncrement"
                          @click.stop="stepTravelers(1)"
                        />
                      </template>
                    </q-input>
                  </div>
                  <div class="col-12 col-sm-6">
                    <p class="field-note">Counting yourself. You can accept between 2 and 100 travelers.</p>
                  </div>
                </div>
              </section>

              <q-separator class="section-divider" />

              <!-- Budget -->
              <section class="form-section">
                <div class="section-head">
                  <q-icon name="payments" size="18px" />
                  <span>Budget per person</span>
                  <span class="optional-tag">Optional</span>
                </div>

                <div class="row q-col-gutter-md">
                  <div class="col-12 col-sm-6">
                    <q-input
                      v-model.number="form.budget_min"
                      label="Minimum"
                      outlined
                      no-error-icon
                      type="number"
                      inputmode="numeric"
                      prefix="PKR"
                      :rules="[val => !val || val >= 0 || 'Must be positive']"
                    />
                  </div>
                  <div class="col-12 col-sm-6">
                    <q-input
                      v-model.number="form.budget_max"
                      label="Maximum"
                      outlined
                      no-error-icon
                      type="number"
                      inputmode="numeric"
                      prefix="PKR"
                      :rules="[
                        val => !val || val >= 0 || 'Must be positive',
                        val => !val || !form.budget_min || val >= form.budget_min || 'Must be at least the minimum'
                      ]"
                    />
                  </div>
                </div>
                <p class="field-note q-mt-sm">
                  Giving a range helps travelers know if the trip fits them.
                </p>
              </section>
            </div>
          </q-step>

          <!-- ── Step 3: Character ──────────────────────────────── -->
          <q-step :name="3" title="Character" icon="tune">
            <div class="step-body">
              <section class="form-section">
                <div class="section-head">
                  <q-icon name="explore" size="18px" />
                  <span>What kind of trip is it?</span>
                </div>

                <div class="type-grid">
                  <button
                    v-for="opt in typeOptions"
                    :key="opt.value"
                    type="button"
                    class="type-card"
                    :class="{ 'type-card--active': form.type === opt.value }"
                    @click="form.type = opt.value"
                  >
                    <q-icon :name="opt.icon" size="22px" />
                    <span>{{ opt.label }}</span>
                  </button>
                </div>
              </section>

              <q-separator class="section-divider" />

              <section class="form-section">
                <div class="section-head">
                  <q-icon name="visibility" size="18px" />
                  <span>Who can join?</span>
                </div>

                <div class="visibility-list">
                  <button
                    v-for="opt in visibilityOptions"
                    :key="opt.value"
                    type="button"
                    class="visibility-row"
                    :class="{ 'visibility-row--active': form.visibility === opt.value }"
                    @click="form.visibility = opt.value"
                  >
                    <q-icon :name="opt.icon" size="20px" class="visibility-icon" />
                    <span class="visibility-text">
                      <strong>{{ opt.label }}</strong>
                      <small>{{ opt.hint }}</small>
                    </span>
                    <q-icon
                      :name="form.visibility === opt.value ? 'radio_button_checked' : 'radio_button_unchecked'"
                      size="20px"
                      :color="form.visibility === opt.value ? 'primary' : 'grey-5'"
                    />
                  </button>
                </div>
              </section>

              <!-- Summary -->
              <div class="summary-card">
                <div class="summary-head">
                  <q-icon name="fact_check" size="18px" />
                  <span>Trip summary</span>
                </div>
                <div class="summary-grid">
                  <div class="summary-item">
                    <span class="summary-label">Title</span>
                    <span class="summary-value">{{ form.title || '—' }}</span>
                  </div>
                  <div class="summary-item">
                    <span class="summary-label">Destination</span>
                    <span class="summary-value">{{ destinationName }}</span>
                  </div>
                  <div class="summary-item">
                    <span class="summary-label">Dates</span>
                    <span class="summary-value">{{ dateSummary }}</span>
                  </div>
                  <div class="summary-item">
                    <span class="summary-label">Travelers</span>
                    <span class="summary-value">{{ form.max_travelers || '—' }}</span>
                  </div>
                  <div class="summary-item">
                    <span class="summary-label">Budget</span>
                    <span class="summary-value">{{ budgetSummary }}</span>
                  </div>
                  <div class="summary-item">
                    <span class="summary-label">Type</span>
                    <span class="summary-value text-capitalize">{{ form.type || '—' }}</span>
                  </div>
                </div>
              </div>
            </div>
          </q-step>

          <!-- Navigation -->
          <template v-slot:navigation>
            <div class="wizard-nav">
              <q-btn
                v-if="step > 1"
                flat
                no-caps
                color="primary"
                label="Back"
                icon="arrow_back"
                @click="step--"
              />
              <div v-else />

              <div class="nav-right">
                <span class="step-count">Step {{ step }} of 3</span>
                <q-btn
                  v-if="step < 3"
                  color="primary"
                  label="Continue"
                  icon-right="arrow_forward"
                  no-caps
                  unelevated
                  rounded
                  @click="nextStep"
                />
                <q-btn
                  v-else
                  color="primary"
                  label="Create Trip"
                  icon="rocket_launch"
                  no-caps
                  unelevated
                  rounded
                  :loading="loading"
                  @click="submitForm"
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
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { api } from 'src/boot/axios'
import { useTripStore } from 'src/stores/tripStore'
import ImageUpload from 'components/ImageUpload.vue'

const router = useRouter()
const $q = useQuasar()
const tripStore = useTripStore()

const step = ref(1)
const loading = ref(false)
const destinationOptions = ref([])
const selectedDestination = ref(null)

const form = ref({
  title: '',
  cover_image: null,
  description: '',
  start_date: '',
  end_date: '',
  max_travelers: null,
  budget_min: null,
  budget_max: null,
  type: '',
  visibility: 'public'
})

const typeOptions = [
  { label: 'Adventure',   value: 'adventure',   icon: 'hiking' },
  { label: 'Cultural',    value: 'cultural',    icon: 'temple_buddhist' },
  { label: 'Budget',      value: 'budget',      icon: 'savings' },
  { label: 'Luxury',      value: 'luxury',      icon: 'diamond' },
  { label: 'Backpacking', value: 'backpacking', icon: 'backpack' }
]

const visibilityOptions = [
  { label: 'Public',      value: 'public',      icon: 'public',       hint: 'Anyone can find and join your trip.' },
  { label: 'Women Only',  value: 'women_only',  icon: 'female',       hint: 'Only women can join. Shown with a Women Only badge.' },
  { label: 'Invite Only', value: 'invite_only', icon: 'lock',         hint: 'People request to join and you approve each one.' }
]

const destinationName = computed(() => {
  if (!selectedDestination.value) return '—'
  const dest = destinationOptions.value.find(d => d.id === selectedDestination.value)
  return dest ? dest.name : '—'
})

const nights = computed(() => {
  if (!form.value.start_date || !form.value.end_date) return 0
  const start = new Date(form.value.start_date)
  const end = new Date(form.value.end_date)
  const diff = Math.round((end - start) / 86400000)
  return diff > 0 ? diff : 0
})

const dateSummary = computed(() => {
  if (!form.value.start_date || !form.value.end_date) return '—'
  return `${form.value.start_date} → ${form.value.end_date}`
})

const budgetSummary = computed(() => {
  const { budget_min: min, budget_max: max } = form.value
  if (!min && !max) return 'Not set'
  if (min && max) return `PKR ${Number(min).toLocaleString()} – ${Number(max).toLocaleString()}`
  if (min) return `From PKR ${Number(min).toLocaleString()}`
  return `Up to PKR ${Number(max).toLocaleString()}`
})

const canDecrement = computed(() => Number(form.value.max_travelers) > 2)
const canIncrement = computed(() => !form.value.max_travelers || Number(form.value.max_travelers) < 100)

const stepTravelers = (delta) => {
  const current = Number(form.value.max_travelers) || 1
  const next = Math.min(100, Math.max(2, current + delta))
  form.value.max_travelers = next
}

const today = new Date().toISOString().slice(0, 10).replace(/-/g, '/')

const futureDates = (date) => date >= today

const afterStartDate = (date) => {
  if (!form.value.start_date) return date >= today
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

const warn = (message) => $q.notify({ color: 'warning', message, icon: 'warning', position: 'top' })

const nextStep = () => {
  if (step.value === 1) {
    if (!form.value.title || !selectedDestination.value) {
      warn('Please add a title and pick a destination')
      return
    }
  }
  if (step.value === 2) {
    if (!form.value.start_date || !form.value.end_date || !form.value.max_travelers) {
      warn('Please fill in the dates and number of travelers')
      return
    }
    if (form.value.end_date < form.value.start_date) {
      warn('End date must be after the start date')
      return
    }
  }
  step.value++
}

const submitForm = async () => {
  if (!form.value.type || !form.value.visibility) {
    warn('Please choose a trip type and who can join')
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
    $q.notify({ color: 'positive', message: 'Trip created successfully!', icon: 'check_circle', position: 'top' })
    router.push(`/trips/${result.data.id}`)
  } catch (err) {
    const errors = err.response?.data?.errors
    if (errors) {
      const firstError = Object.values(errors)[0]?.[0]
      $q.notify({ color: 'negative', message: firstError || 'Validation failed', icon: 'error', position: 'top' })
    } else {
      $q.notify({
        color: 'negative',
        message: err.response?.data?.message || 'Failed to create trip',
        icon: 'error',
        position: 'top'
      })
    }
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.create-trip-page {
  background: #faf8fc;
  padding: 24px 16px 64px;
}

.wizard-shell {
  max-width: 760px;
  margin: 0 auto;
}

/* ── Header ─────────────────────────────────────────── */
.page-head {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 20px;
}
.page-title {
  margin: 0;
  font-size: 30px;
  line-height: 1.15;
  font-weight: 600;
  letter-spacing: -0.02em;
  background: linear-gradient(95deg, #3d1152 0%, #6b2d5e 45%, #8e3d8a 100%);
  -webkit-background-clip: text; background-clip: text; color: transparent;
}
.page-sub {
  margin: 4px 0 0;
  font-size: 14px;
  color: #7a6a82;
}

/* ── Card ───────────────────────────────────────────── */
.wizard-card {
  border-radius: 16px;
  border-color: #ece6f0;
  background: #fff;
  box-shadow: 0 1px 2px rgba(43, 27, 51, 0.04), 0 8px 24px rgba(43, 27, 51, 0.06);
  overflow: hidden;
}

.step-body {
  padding-top: 4px;
}

/* ── Sections ───────────────────────────────────────── */
.form-section + .form-section { margin-top: 4px; }

.section-head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 16px;
  font-size: 15px;
  font-weight: 600;
  color: #2b1b33;
}
.section-head .q-icon { color: var(--q-primary); }

.optional-tag {
  margin-left: 2px;
  padding: 2px 8px;
  border-radius: 999px;
  background: #f1ecf5;
  color: #7a6a82;
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.02em;
}

.section-divider {
  margin: 28px 0;
  background: #f0eaf4;
}

.field-note {
  margin: 0;
  font-size: 12.5px;
  line-height: 1.45;
  color: #7a6a82;
}

/* ── Dates ──────────────────────────────────────────── */
.date-field { position: relative; }

/* These are pickers, not disabled inputs — Quasar dashes readonly borders
   by default, which makes them read as greyed out. Keep them solid. */
.date-field :deep(.q-field--readonly .q-field__control:before) {
  border-style: solid;
}
.date-field :deep(.q-field--readonly .q-field__native) { cursor: pointer; }

.duration-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 4px;
  padding: 6px 12px;
  border-radius: 999px;
  background: #f3e9f8;
  color: #6a3d7d;
  font-size: 12.5px;
  font-weight: 500;
}

.fade-slide-enter-active,
.fade-slide-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.fade-slide-enter-from,
.fade-slide-leave-to { opacity: 0; transform: translateY(-4px); }

/* Kill the ugly native number spinners */
.step-body :deep(input[type='number'])::-webkit-outer-spin-button,
.step-body :deep(input[type='number'])::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
.step-body :deep(input[type='number']) { -moz-appearance: textfield; }

.stepper-input :deep(.q-field__prepend),
.stepper-input :deep(.q-field__append) { padding: 0 4px; }

/* ── Trip type cards ────────────────────────────────── */
.type-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 10px;
}
.type-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 16px 10px;
  border: 1.5px solid #e8e0ee;
  border-radius: 12px;
  background: #fff;
  color: #5c4c66;
  font: inherit;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: border-color 0.16s ease, background 0.16s ease, transform 0.16s ease;
}
.type-card:hover { border-color: #c9b3d6; transform: translateY(-1px); }
.type-card--active {
  border-color: var(--q-primary);
  background: #f7f0fa;
  color: var(--q-primary);
}
.type-card--active .q-icon { color: var(--q-primary); }

/* ── Visibility rows ────────────────────────────────── */
.visibility-list { display: flex; flex-direction: column; gap: 10px; }

.visibility-row {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 14px 16px;
  border: 1.5px solid #e8e0ee;
  border-radius: 12px;
  background: #fff;
  text-align: left;
  font: inherit;
  cursor: pointer;
  transition: border-color 0.16s ease, background 0.16s ease;
}
.visibility-row:hover { border-color: #c9b3d6; }
.visibility-row--active { border-color: var(--q-primary); background: #f7f0fa; }
.visibility-icon { color: #8a7696; flex-shrink: 0; }
.visibility-row--active .visibility-icon { color: var(--q-primary); }

.visibility-text { display: flex; flex-direction: column; gap: 2px; flex: 1; min-width: 0; }
.visibility-text strong { font-size: 14px; font-weight: 600; color: #2b1b33; }
.visibility-text small { font-size: 12.5px; line-height: 1.4; color: #7a6a82; }

/* ── Summary ────────────────────────────────────────── */
.summary-card {
  margin-top: 28px;
  padding: 18px;
  border-radius: 12px;
  background: #faf7fc;
  border: 1px solid #ece6f0;
}
.summary-head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 14px;
  font-size: 14px;
  font-weight: 600;
  color: #2b1b33;
}
.summary-head .q-icon { color: var(--q-primary); }

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 14px 20px;
}
.summary-item { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.summary-label {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #9b8aa5;
}
.summary-value {
  font-size: 13.5px;
  color: #2b1b33;
  overflow-wrap: anywhere;
}

/* ── Navigation ─────────────────────────────────────── */
.wizard-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 8px;
  padding-top: 20px;
  border-top: 1px solid #f0eaf4;
}
.nav-right { display: flex; align-items: center; gap: 14px; }
.step-count { font-size: 12.5px; color: #9b8aa5; }

@media (max-width: 599px) {
  .page-title { font-size: 24px; }
  .step-count { display: none; }
  .create-trip-page { padding: 16px 12px 48px; }
}
</style>
