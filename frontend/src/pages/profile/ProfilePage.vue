<template>
  <q-page padding class="flex flex-center bg-grey-2">
    <!-- Loading State -->
    <div v-if="loading" class="row justify-center q-py-xl">
      <q-spinner-dots color="primary" size="4em" />
    </div>

    <!-- Error State -->
    <div v-else-if="fetchError" class="text-center q-py-xl">
      <q-icon name="error_outline" size="64px" color="negative" />
      <div class="text-h6 q-mt-md">{{ fetchError }}</div>
      <q-btn color="primary" label="Go Home" to="/" class="q-mt-md" />
    </div>

    <q-card v-else class="q-pa-md shadow-2" style="width: 100%; max-width: 600px; border-radius: 12px">
      <!-- VIEW MODE -->
      <div v-if="!isEditMode">
        <q-card-section class="text-center">
          <q-avatar size="100px" color="grey-4" text-color="grey-8">
            <img v-if="profileUser.avatar" :src="profileUser.avatar" />
            <span v-else class="text-h3">{{ profileUser.name.charAt(0).toUpperCase() }}</span>
          </q-avatar>
          <div class="text-h5 q-mt-md text-weight-bold">{{ profileUser.name }}</div>
          <div v-if="profileUser.city" class="text-subtitle1 text-grey-8">
            <q-icon name="place" /> {{ profileUser.city }}
          </div>
        </q-card-section>

        <q-card-section>
          <div v-if="profileUser.bio" class="text-body1 text-center q-mb-md text-grey-9" style="font-style: italic;">
            "{{ profileUser.bio }}"
          </div>

          <div class="row justify-center q-gutter-sm q-mb-md">
            <q-badge v-if="profileUser.gender" color="secondary" outline class="q-pa-sm text-subtitle2 capitalize">
              {{ profileUser.gender }}
            </q-badge>
            <q-badge v-if="profileUser.type" color="primary" class="q-pa-sm text-subtitle2 capitalize">
              {{ profileUser.type }}
            </q-badge>
          </div>

          <div v-if="profileUser.preferences?.travel_style?.length" class="q-mt-md">
            <div class="text-subtitle2 text-grey-8 q-mb-sm">Travel Style:</div>
            <div class="row q-gutter-xs">
              <q-badge
                v-for="style in profileUser.preferences.travel_style"
                :key="style"
                color="primary"
                class="q-px-sm q-py-xs capitalize"
              >{{ style }}</q-badge>
            </div>
          </div>

          <div v-if="profileUser.preferences?.regions?.length" class="q-mt-sm">
            <div class="text-subtitle2 text-grey-8 q-mb-sm">Regions of Interest:</div>
            <div class="row q-gutter-xs">
              <q-badge
                v-for="region in profileUser.preferences.regions"
                :key="region"
                color="deep-purple"
                class="q-px-sm q-py-xs"
              >{{ region }}</q-badge>
            </div>
          </div>
        </q-card-section>

        <q-card-section class="text-center text-grey-6 text-caption">
          Member since {{ formatDate(profileUser.created_at) }}
        </q-card-section>

        <q-card-actions align="center" v-if="isOwnProfile">
          <q-btn color="primary" label="Edit Profile" icon="edit" @click="toggleEditMode" />
        </q-card-actions>
      </div>

      <!-- EDIT MODE -->
      <div v-else>
        <q-card-section class="row items-center justify-between">
          <div class="text-h6 text-primary">Edit Profile</div>
          <q-btn flat round icon="close" @click="cancelEdit" />
        </q-card-section>

        <q-card-section>
          <q-form @submit.prevent="onSave" class="q-gutter-md">
            <q-input
              v-model="form.name"
              label="Name"
              outlined
              :rules="[val => !!val || 'Name is required', val => val.length <= 100 || 'Max 100 characters']"
            />
            
            <q-input
              v-model="form.bio"
              type="textarea"
              label="Bio"
              outlined
              autogrow
              :rules="[val => !val || val.length <= 500 || 'Max 500 characters']"
            />
            
            <q-input
              v-model="form.city"
              label="City"
              outlined
              :rules="[val => !val || val.length <= 100 || 'Max 100 characters']"
            />

            <q-input
              v-model="form.phone"
              type="tel"
              label="Phone"
              outlined
            />

            <q-select
              v-model="form.gender"
              :options="['male', 'female', 'other']"
              label="Gender"
              outlined
              clearable
              class="capitalize"
            />

            <q-separator class="q-my-xs" />

            <div>
              <div class="text-caption text-grey-7 q-mb-xs">Travel Style</div>
              <div class="row q-gutter-xs">
                <q-chip
                  v-for="style in styleOptions"
                  :key="style"
                  clickable
                  :color="form.preferences.travel_style.includes(style) ? 'primary' : 'grey-3'"
                  :text-color="form.preferences.travel_style.includes(style) ? 'white' : 'grey-8'"
                  @click="togglePref(form.preferences.travel_style, style)"
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
                  :color="form.preferences.regions.includes(region) ? 'deep-purple' : 'grey-3'"
                  :text-color="form.preferences.regions.includes(region) ? 'white' : 'grey-8'"
                  @click="togglePref(form.preferences.regions, region)"
                >{{ region }}</q-chip>
              </div>
            </div>

            <div v-if="saveError" class="text-negative text-center q-mt-sm">
              {{ saveError }}
            </div>

            <div class="row justify-end q-mt-lg q-gutter-sm">
              <q-btn flat label="Cancel" color="grey-8" @click="cancelEdit" />
              <q-btn type="submit" label="Save" color="primary" :loading="saving" />
            </div>
          </q-form>
        </q-card-section>
      </div>
    </q-card>
  </q-page>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from 'src/stores/authStore'
import { api } from 'src/boot/axios'
import { useQuasar } from 'quasar'

const route = useRoute()
const authStore = useAuthStore()
const $q = useQuasar()

const loading = ref(true)
const fetchError = ref('')
const profileUser = ref(null)

const isEditMode = ref(false)
const saving = ref(false)
const saveError = ref('')

const styleOptions = ['adventure', 'cultural', 'budget', 'luxury', 'backpacking']
const regionOptions = ['Punjab', 'Sindh', 'KPK', 'Balochistan', 'Gilgit-Baltistan', 'AJK', 'Islamabad']

const form = ref({
  name: '',
  bio: '',
  city: '',
  phone: '',
  gender: null,
  preferences: { travel_style: [], regions: [] },
})

// Check if viewing own profile
const isOwnProfile = computed(() => {
  if (!authStore.user) return false
  return profileUser.value && profileUser.value.id === authStore.user.id
})

onMounted(async () => {
  await loadProfile()
})

const loadProfile = async () => {
  loading.value = true
  fetchError.value = ''
  try {
    const requestedId = route.params.id

    if (!requestedId) {
      // Just load authenticated user
      profileUser.value = authStore.user
      loading.value = false
      return
    }

    // Load user by ID
    const response = await api.get(`/api/v1/users/${requestedId}`)
    profileUser.value = response.data.data

  } catch (error) {
    if (error.response?.status === 404) {
      fetchError.value = 'User not found.'
    } else {
      fetchError.value = 'Failed to load profile.'
    }
  } finally {
    loading.value = false
  }
}

const togglePref = (arr, value) => {
  const i = arr.indexOf(value)
  if (i === -1) arr.push(value)
  else arr.splice(i, 1)
}

const toggleEditMode = () => {
  form.value = {
    name: profileUser.value.name || '',
    bio: profileUser.value.bio || '',
    city: profileUser.value.city || '',
    phone: profileUser.value.phone || '',
    gender: profileUser.value.gender || null,
    preferences: {
      travel_style: [...(profileUser.value.preferences?.travel_style || [])],
      regions: [...(profileUser.value.preferences?.regions || [])],
    },
  }
  saveError.value = ''
  isEditMode.value = true
}

const cancelEdit = () => {
  isEditMode.value = false
}

const onSave = async () => {
  saving.value = true
  saveError.value = ''

  try {
    await authStore.updateProfile(form.value)
    
    // Update local view model
    profileUser.value = authStore.user
    
    $q.notify({
      color: 'positive',
      icon: 'check_circle',
      message: 'Profile updated successfully'
    })
    
    isEditMode.value = false
  } catch (error) {
    if (error.response?.data?.errors) {
      const firstError = Object.values(error.response.data.errors)[0][0]
      saveError.value = firstError || 'Update failed.'
    } else {
      saveError.value = error.response?.data?.message || 'Update failed. Please try again.'
    }
  } finally {
    saving.value = false
  }
}

const formatDate = (dateString) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  return date.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' })
}
</script>

<style scoped>
.capitalize {
  text-transform: capitalize;
}
</style>
