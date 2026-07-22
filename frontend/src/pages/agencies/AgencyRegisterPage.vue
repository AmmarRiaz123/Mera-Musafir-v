<template>
  <q-page padding class="flex flex-center">
    <q-card style="width: 100%; max-width: 560px" flat bordered>
      <q-card-section class="bg-deep-purple text-white q-pa-lg">
        <h1 class="page-title page-title--sm">Register Your Agency</h1>
        <div class="text-body2 opacity-80 q-mt-xs">
          Create your agency profile to start listing travel packages
        </div>
      </q-card-section>

      <!-- Wrong user type guard -->
      <q-card-section v-if="authStore.user?.type !== 'agency'" class="text-center q-py-xl">
        <q-icon name="warning" color="warning" size="3em" />
        <div class="text-h6 q-mt-md">Agency Account Required</div>
        <div class="text-body2 text-grey-6 q-mt-xs">
          You need to register with an agency account type to create an agency profile.
        </div>
      </q-card-section>

      <!-- Already has agency -->
      <q-card-section v-else-if="agencyStore.myAgency" class="text-center q-py-xl">
        <q-icon name="check_circle" color="positive" size="3em" />
        <div class="text-h6 q-mt-md">Agency Already Registered</div>
        <div class="text-body2 text-grey-6 q-mt-xs">You already have an agency profile.</div>
        <q-btn
          class="q-mt-lg"
          color="deep-purple"
          unelevated
          rounded
          label="Go to Dashboard"
          :to="`/agencies/${agencyStore.myAgency.slug}/dashboard`"
        />
      </q-card-section>

      <!-- Registration form -->
      <template v-else>
        <q-card-section class="column q-gutter-md">
          <q-input
            v-model="form.business_name"
            label="Business Name *"
            outlined dense
            :rules="[v => !!v || 'Required']"
          />
          <q-input
            v-model="form.description"
            label="Description"
            outlined dense
            type="textarea"
            :rows="3"
            hint="Tell travelers about your agency, specialities, and experience"
          />
          <q-input
            v-model="form.contact_phone"
            label="Contact Phone"
            outlined dense
            hint="e.g. +92 321 1234567"
          />
          <q-input
            v-model="form.contact_email"
            label="Contact Email"
            outlined dense type="email"
          />
          <q-input
            v-model="form.license_number"
            label="License Number (optional)"
            outlined dense
            hint="PTDC or provincial tourism license number"
          />

          <q-banner rounded class="bg-blue-1 text-blue-9 text-body2">
            <template v-slot:avatar><q-icon name="info" color="blue" /></template>
            Your agency will be reviewed by our team before being listed publicly. This usually takes 1–2 business days.
          </q-banner>
        </q-card-section>

        <q-card-actions align="right" class="q-pa-md">
          <q-btn flat label="Cancel" @click="$router.back()" />
          <q-btn
            unelevated
            color="deep-purple"
            rounded
            label="Register Agency"
            :loading="submitting"
            :disable="!form.business_name"
            @click="submit"
          />
        </q-card-actions>
      </template>
    </q-card>
  </q-page>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAuthStore } from 'src/stores/authStore'
import { useAgencyStore } from 'src/stores/agencyStore'

const router = useRouter()
const $q = useQuasar()
const authStore = useAuthStore()
const agencyStore = useAgencyStore()

const submitting = ref(false)
const form = reactive({
  business_name: '',
  description: '',
  contact_phone: '',
  contact_email: '',
  license_number: '',
})

onMounted(() => agencyStore.fetchMyAgency())

const submit = async () => {
  if (!form.business_name) return
  submitting.value = true
  try {
    await agencyStore.registerAgency({ ...form })
    $q.notify({ color: 'positive', message: 'Agency registered! Awaiting verification.', icon: 'check' })
    router.push(`/agencies/${agencyStore.myAgency.slug}/dashboard`)
  } catch (err) {
    $q.notify({ color: 'negative', message: err.response?.data?.message || 'Registration failed', icon: 'error' })
  } finally {
    submitting.value = false
  }
}
</script>
