<template>
  <q-page class="flex flex-center column q-gutter-md">
    <div class="text-h4">Mera Musafir</div>

    <q-btn
      color="primary"
      label="Test API Connection"
      @click="testConnection"
      :loading="loading"
    />

    <div v-if="response" class="text-positive text-h6">
      {{ response }}
    </div>

    <div v-if="error" class="text-negative">
      {{ error }}
    </div>
  </q-page>
</template>

<script setup>
import { ref } from 'vue'
import { api } from 'src/boot/axios'

const loading  = ref(false)
const response = ref(null)
const error    = ref(null)

async function testConnection() {
  loading.value  = true
  response.value = null
  error.value    = null

  try {
    const { data } = await api.get('/api/v1/ping')
    response.value = data.message
  } catch (err) {
    error.value = 'Connection failed: ' + err.message
  } finally {
    loading.value = false
  }
}
</script>