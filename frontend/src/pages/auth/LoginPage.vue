<template>
  <q-layout view="lHh Lpr lFf">
    <q-page-container>
      <q-page class="flex flex-center bg-grey-2">
        <q-card class="q-pa-md shadow-2" style="width: 100%; max-width: 400px; border-radius: 12px">
          <q-card-section class="text-center">
            <div class="text-h5 text-primary text-weight-bold">Login</div>
            <div class="text-grey-7">Sign in to Mera Musafir</div>
          </q-card-section>

          <q-card-section>
            <q-form @submit.prevent="onSubmit" class="q-gutter-md">
              <q-input
                v-model="form.email"
                type="email"
                label="Email"
                outlined
                lazy-rules
                :rules="[val => !!val || 'Email is required']"
              />

              <q-input
                v-model="form.password"
                type="password"
                label="Password"
                outlined
                lazy-rules
                :rules="[val => !!val || 'Password is required']"
              />

              <div v-if="errorMessage" class="text-negative text-center q-mt-sm">
                {{ errorMessage }}
              </div>

              <div class="q-mt-md">
                <q-btn
                  label="Login"
                  type="submit"
                  color="primary"
                  class="full-width"
                  :loading="isLoading"
                />
              </div>
            </q-form>
          </q-card-section>

          <q-card-section class="text-center q-pt-none">
            <span class="text-grey-8">Don't have an account? </span>
            <router-link to="/register" class="text-primary text-weight-medium" style="text-decoration: none">
              Register here
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
  email: '',
  password: ''
})

const isLoading = ref(false)
const errorMessage = ref('')

const onSubmit = async () => {
  isLoading.value = true
  errorMessage.value = ''
  
  try {
    await authStore.login(form)
    router.push('/')
  } catch (error) {
    if (error.response?.data?.errors?.email) {
      errorMessage.value = error.response.data.errors.email[0]
    } else {
      errorMessage.value = error.response?.data?.message || 'Login failed. Please try again.'
    }
  } finally {
    isLoading.value = false
  }
}
</script>
