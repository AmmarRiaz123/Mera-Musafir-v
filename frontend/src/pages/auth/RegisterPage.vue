<template>
  <q-layout view="lHh Lpr lFf">
    <q-page-container>
      <q-page class="flex flex-center bg-grey-2 q-pa-md">
        <q-card class="q-pa-md shadow-2" style="width: 100%; max-width: 460px; border-radius: 12px">
          <q-card-section class="text-center">
            <div class="text-h5 text-primary text-weight-bold">Join Mera Musafir</div>
            <div class="text-grey-7">Create your account</div>
          </q-card-section>

          <q-card-section>
            <q-form @submit.prevent="onSubmit" class="q-gutter-md">

              <!-- Account Type -->
              <div>
                <div class="text-caption text-grey-7 q-mb-sm">I am a...</div>
                <q-btn-toggle
                  v-model="form.type"
                  spread
                  unelevated
                  toggle-color="primary"
                  color="white"
                  text-color="grey-8"
                  :options="[
                    { label: 'Traveler', value: 'traveler', icon: 'hiking' },
                    { label: 'Travel Agency', value: 'agency', icon: 'business' },
                  ]"
                  class="full-width type-toggle"
                />
              </div>

              <!-- Basic Info -->
              <q-input
                v-model="form.name"
                label="Full Name"
                outlined
                lazy-rules
                :rules="[val => !!val || 'Name is required']"
              />

              <q-input
                v-model="form.email"
                type="email"
                label="Email"
                outlined
                lazy-rules
                :rules="[val => !!val || 'Email is required']"
              />

              <q-input
                v-model="form.phone"
                type="tel"
                label="Phone (optional)"
                outlined
              />

              <q-input
                v-model="form.password"
                type="password"
                label="Password"
                outlined
                lazy-rules
                :rules="[val => !!val || 'Password is required', val => val.length >= 8 || 'Min 8 characters']"
              />

              <q-input
                v-model="form.password_confirmation"
                type="password"
                label="Confirm Password"
                outlined
                lazy-rules
                :rules="[
                  val => !!val || 'Please confirm your password',
                  val => val === form.password || 'Passwords do not match'
                ]"
              />

              <!-- Optional profile info -->
              <q-separator class="q-my-xs" />
              <div class="text-caption text-grey-5">Optional — helps us personalise your experience</div>

              <div class="row q-col-gutter-sm">
                <div class="col-8">
                  <q-input v-model="form.city" label="City" outlined />
                </div>
                <div class="col-4">
                  <q-select
                    v-model="form.gender"
                    :options="genderOptions"
                    label="Gender"
                    outlined
                    clearable
                    emit-value
                    map-options
                  />
                </div>
              </div>

              <!-- Travel preferences (traveler only) -->
              <template v-if="form.type === 'traveler'">
                <div>
                  <div class="text-caption text-grey-7 q-mb-xs">Travel Style (pick all that apply)</div>
                  <div class="row q-gutter-xs">
                    <q-chip
                      v-for="style in styleOptions"
                      :key="style"
                      clickable
                      :selected="form.travelStyle.includes(style)"
                      :color="form.travelStyle.includes(style) ? 'primary' : 'grey-3'"
                      :text-color="form.travelStyle.includes(style) ? 'white' : 'grey-8'"
                      @click="toggle(form.travelStyle, style)"
                      class="capitalize"
                    >{{ style }}</q-chip>
                  </div>
                </div>

                <div>
                  <div class="text-caption text-grey-7 q-mb-xs">Regions of Interest</div>
                  <div class="row q-gutter-xs">
                    <q-chip
                      v-for="region in regionOptions"
                      :key="region"
                      clickable
                      :selected="form.regions.includes(region)"
                      :color="form.regions.includes(region) ? 'deep-purple' : 'grey-3'"
                      :text-color="form.regions.includes(region) ? 'white' : 'grey-8'"
                      @click="toggle(form.regions, region)"
                    >{{ region }}</q-chip>
                  </div>
                </div>
              </template>

              <div v-if="errorMessage" class="text-negative text-center q-mt-sm">
                {{ errorMessage }}
              </div>

              <div class="q-mt-md">
                <q-btn
                  label="Create Account"
                  type="submit"
                  color="primary"
                  class="full-width"
                  :loading="isLoading"
                />
              </div>
            </q-form>
          </q-card-section>

          <q-card-section class="text-center q-pt-none">
            <span class="text-grey-8">Already have an account? </span>
            <router-link to="/login" class="text-primary text-weight-medium" style="text-decoration: none">
              Login here
            </router-link>
          </q-card-section>
        </q-card>
      </q-page>
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from 'src/stores/authStore'

const router = useRouter()
const authStore = useAuthStore()

const genderOptions = [
  { label: 'Male', value: 'male' },
  { label: 'Female', value: 'female' },
  { label: 'Other', value: 'other' },
]

const styleOptions = ['adventure', 'cultural', 'budget', 'luxury', 'backpacking']
const regionOptions = ['Punjab', 'Sindh', 'KPK', 'Balochistan', 'Gilgit-Baltistan', 'AJK', 'Islamabad']

const form = reactive({
  type: 'traveler',
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
  city: '',
  gender: null,
  travelStyle: [],
  regions: [],
})

const isLoading = ref(false)
const errorMessage = ref('')

const toggle = (arr, value) => {
  const i = arr.indexOf(value)
  if (i === -1) arr.push(value)
  else arr.splice(i, 1)
}

const onSubmit = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const payload = {
      type: form.type,
      name: form.name,
      email: form.email,
      phone: form.phone || null,
      password: form.password,
      password_confirmation: form.password_confirmation,
      city: form.city || null,
      gender: form.gender || null,
    }
    if (form.type === 'traveler' && (form.travelStyle.length || form.regions.length)) {
      payload.preferences = {
        travel_style: form.travelStyle,
        regions: form.regions,
      }
    }
    await authStore.register(payload)
    router.push('/')
  } catch (error) {
    if (error.response?.data?.errors) {
      const firstError = Object.values(error.response.data.errors)[0][0]
      errorMessage.value = firstError || 'Registration failed.'
    } else {
      errorMessage.value = error.response?.data?.message || 'Registration failed. Please try again.'
    }
  } finally {
    isLoading.value = false
  }
}
</script>

<style scoped>
.type-toggle {
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  overflow: hidden;
}
</style>
