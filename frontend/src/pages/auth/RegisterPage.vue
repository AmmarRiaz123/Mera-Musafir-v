<template>
  <q-layout view="lHh Lpr lFf">
    <q-page-container>
      <q-page class="flex flex-center bg-grey-2 q-pa-md">
        <q-card class="q-pa-md shadow-2" style="width: 100%; max-width: 400px; border-radius: 12px">
          <q-card-section class="text-center">
            <div class="text-h5 text-primary text-weight-bold">Register</div>
            <div class="text-grey-7">Join Mera Musafir</div>
          </q-card-section>

          <q-card-section>
            <q-form @submit.prevent="onSubmit" class="q-gutter-md">
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
                :rules="[val => !!val || 'Password is required']"
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

              <div v-if="errorMessage" class="text-negative text-center q-mt-sm">
                {{ errorMessage }}
              </div>

              <div class="q-mt-md">
                <q-btn
                  label="Register"
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

const form = reactive({
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
  type: 'traveler' // Hardcoded type traveler
})

const isLoading = ref(false)
const errorMessage = ref('')

const onSubmit = async () => {
  isLoading.value = true
  errorMessage.value = ''
  
  try {
    await authStore.register(form)
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
