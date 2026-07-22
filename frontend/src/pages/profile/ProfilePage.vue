<template>
  <q-page padding class="profile-page">
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

    <q-card v-else flat class="pf-card">
      <!-- VIEW MODE -->
      <div v-if="!isEditMode">
        <!-- Header row with settings/actions -->
        <div class="pf-banner">
          <div class="pf-banner-tools">
            <!-- Privacy settings link for own profile -->
            <q-btn v-if="isOwnProfile" flat round icon="settings" color="white" size="sm" to="/privacy">
              <q-tooltip>Privacy Settings</q-tooltip>
            </q-btn>

          <!-- Three-dot menu for other profiles -->
          <template v-if="!isOwnProfile && authStore.isLoggedIn">
            <q-btn flat round icon="more_vert" color="white" size="sm">
              <q-menu anchor="bottom right" self="top right">
                <q-list style="min-width: 180px">
                  <q-item clickable v-close-popup @click="handleBlock">
                    <q-item-section avatar>
                      <q-icon :name="safetyStore.isBlocked(profileUser.id) ? 'person_add' : 'block'" color="grey-8" />
                    </q-item-section>
                    <q-item-section>{{ safetyStore.isBlocked(profileUser.id) ? 'Unblock User' : 'Block User' }}</q-item-section>
                  </q-item>
                  <q-item v-if="profileUser.reported_by_me" clickable disable>
                    <q-item-section avatar>
                      <q-icon name="flag" color="grey-5" />
                    </q-item-section>
                    <q-item-section class="text-grey-6">Reported — under review</q-item-section>
                  </q-item>
                  <q-item v-else clickable v-close-popup @click="reportDialog = true">
                    <q-item-section avatar>
                      <q-icon name="flag" color="negative" />
                    </q-item-section>
                    <q-item-section class="text-negative">Report User</q-item-section>
                  </q-item>
                </q-list>
              </q-menu>
            </q-btn>
          </template>
          </div>
        </div>

        <q-card-section class="pf-identity text-center">
          <q-avatar size="104px" class="pf-avatar">
            <img v-if="profileUser.avatar" :src="profileUser.avatar" />
            <span v-else>{{ profileUser.name.charAt(0).toUpperCase() }}</span>
          </q-avatar>

          <div class="pf-name-line">
            <span class="pf-name">{{ profileUser.name }}</span>
            <q-icon v-if="profileUser.is_verified" name="verified" color="deep-purple" size="20px">
              <q-tooltip>Verified traveller</q-tooltip>
            </q-icon>
            <span v-if="isFriend" class="pf-friend-tag">Friends</span>
          </div>

          <div v-if="profileUser.city" class="pf-city">
            <q-icon name="place" size="14px" />{{ profileUser.city }}
          </div>

          <!-- The social graph, and the way into it -->
          <div class="pf-stats">
            <button type="button" class="pf-stat" @click="openConnections('followers')">
              <span class="pf-stat-n">{{ followersCount }}</span>
              <span class="pf-stat-l">{{ followersCount === 1 ? 'Follower' : 'Followers' }}</span>
            </button>
            <span class="pf-stat-div" />
            <button type="button" class="pf-stat" @click="openConnections('following')">
              <span class="pf-stat-n">{{ followingCount }}</span>
              <span class="pf-stat-l">Following</span>
            </button>
            <span class="pf-stat-div" />
            <button type="button" class="pf-stat" @click="openConnections('friends')">
              <span class="pf-stat-n">{{ friendsCount }}</span>
              <span class="pf-stat-l">{{ friendsCount === 1 ? 'Friend' : 'Friends' }}</span>
            </button>
          </div>
        </q-card-section>

        <q-card-section>
          <div v-if="profileUser.bio" class="text-body1 text-center q-mb-md text-grey-9" style="font-style: italic;">
            "{{ profileUser.bio }}"
          </div>

          <div class="pf-chips">
            <span v-if="profileUser.gender" class="pf-chip capitalize">{{ profileUser.gender }}</span>
            <span v-if="profileUser.type" class="pf-chip pf-chip--solid capitalize">{{ profileUser.type }}</span>
          </div>

          <div v-if="profileUser.preferences?.travel_style?.length" class="pf-section">
            <div class="pf-label">Travel style</div>
            <div class="pf-chips pf-chips--left">
              <span
                v-for="style in profileUser.preferences.travel_style"
                :key="style"
                class="pf-chip pf-chip--solid capitalize"
              >{{ style }}</span>
            </div>
          </div>

          <div v-if="profileUser.preferences?.regions?.length" class="pf-section">
            <div class="pf-label">Regions of interest</div>
            <div class="pf-chips pf-chips--left">
              <span
                v-for="region in profileUser.preferences.regions"
                :key="region"
                class="pf-chip"
              >{{ region }}</span>
            </div>
          </div>
        </q-card-section>

        <q-card-section class="pf-since">
          <q-icon name="event" size="13px" />Member since {{ formatDate(profileUser.created_at) }}
        </q-card-section>

        <q-card-actions align="center" v-if="isOwnProfile" class="q-pb-lg">
          <q-btn
            unelevated rounded no-caps color="deep-purple"
            icon="edit" label="Edit profile" @click="toggleEditMode"
          />
        </q-card-actions>

        <!-- Actions for other profiles -->
        <q-card-actions align="center" v-if="!isOwnProfile && authStore.isLoggedIn">
          <q-btn
            :label="isFollowing ? 'Unfollow' : 'Follow'"
            :color="isFollowing ? 'grey-7' : 'primary'"
            :outline="isFollowing"
            unelevated
            :loading="followLoading"
            @click="handleFollow"
          />
          <q-btn
            color="deep-purple"
            icon="chat"
            label="Message"
            flat
            @click="handleMessage"
          />
        </q-card-actions>
      </div>

      <ConnectionsDialog
        v-if="profileUser"
        v-model="connectionsOpen"
        :user-id="profileUser.id"
        :user-name="profileUser.name"
        :is-self="isOwnProfile"
        :initial-tab="connectionsTab"
        :known-counts="{ followers: followersCount, following: followingCount, friends: friendsCount }"
        @changed="refreshCounts"
      />

      <!-- Report dialog -->
      <ReportDialog
        v-if="profileUser && !isOwnProfile"
        v-model="reportDialog"
        :reported-id="profileUser.id"
        reported-type="user"
        @reported="profileUser.reported_by_me = true"
      />

      <!-- EDIT MODE -->
      <div v-if="isEditMode">
        <q-card-section class="row items-center justify-between">
          <div class="text-h6 text-primary">Edit Profile</div>
          <q-btn flat round icon="close" @click="cancelEdit" />
        </q-card-section>

        <q-card-section>
          <q-form @submit.prevent="onSave" class="q-gutter-md">
            <div class="column items-center q-mb-sm">
              <ImageUpload
                v-model="form.avatar"
                type="avatar"
                round
                icon="add_a_photo"
                label="Photo"
              />
            </div>

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
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from 'src/stores/authStore'
import { useSafetyStore } from 'src/stores/safetyStore'
import { useSocialStore } from 'src/stores/socialStore'
import { useNotificationStore } from 'src/stores/notificationStore'
import ImageUpload from 'components/ImageUpload.vue'
import { useQuasar } from 'quasar'
import ReportDialog from 'src/components/ReportDialog.vue'
import ConnectionsDialog from 'src/components/ConnectionsDialog.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const safetyStore = useSafetyStore()
const socialStore = useSocialStore()
const notificationStore = useNotificationStore()
const $q = useQuasar()

// Follow state is derived from the store's currentUser, which is hydrated
// straight from the API on every load — never from stale local state.
const isFollowing = computed(() => socialStore.currentUser?.is_following ?? false)
const isFriend = computed(() => socialStore.currentUser?.is_friend ?? false)
const followersCount = computed(() => socialStore.currentUser?.followers_count ?? 0)
const followingCount = computed(() => socialStore.currentUser?.following_count ?? 0)
const friendsCount   = computed(() => socialStore.currentUser?.friends_count ?? 0)

const connectionsOpen = ref(false)
const connectionsTab = ref('followers')

const openConnections = (tab) => {
  connectionsTab.value = tab
  connectionsOpen.value = true
}

// Removing a follower or blocking someone changes the counts behind the card.
const refreshCounts = () => {
  if (profileUser.value?.id) socialStore.fetchUser(profileUser.value.id)
}
const followLoading = ref(false)

const reportDialog = ref(false)

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
  if (authStore.isLoggedIn) safetyStore.fetchBlockList()
})

const loadProfile = async () => {
  loading.value = true
  fetchError.value = ''
  try {
    const requestedId = route.params.id

    // Own profile used to read straight from authStore, which carries no
    // social-graph counts — so the stats row would sit at zero on your own page.
    const id = requestedId ?? authStore.user?.id

    if (!id) {
      profileUser.value = authStore.user
      loading.value = false
      return
    }

    // Load user by ID through the store so follow state stays in one place
    profileUser.value = await socialStore.fetchUser(id)

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

const handleBlock = async () => {
  try {
    const result = await safetyStore.toggleBlock(profileUser.value.id, profileUser.value)
    $q.notify({ color: 'info', icon: result.blocked ? 'block' : 'person_add', message: result.message })
  } catch {
    $q.notify({ color: 'negative', message: 'Action failed' })
  }
}

const handleFollow = async () => {
  followLoading.value = true
  try {
    // The store patches currentUser; isFollowing/followersCount update reactively.
    const res = await socialStore.followUser(profileUser.value.id)
    $q.notify({ type: 'positive', message: res.message, position: 'top' })
  } catch {
    $q.notify({ type: 'negative', message: 'Action failed', position: 'top' })
  } finally {
    followLoading.value = false
  }
}

const handleMessage = async () => {
  try {
    const conv = await socialStore.startConversation(profileUser.value.id)
    router.push(`/messages/${conv.id}`)
  } catch (e) {
    const data = e.response?.data
    if (data?.requested) {
      notificationStore.promptRequest({
        id: profileUser.value.id,
        name: profileUser.value.name,
        avatar: profileUser.value.avatar,
      })
    } else {
      $q.notify({ type: 'negative', message: data?.message || 'Cannot start conversation', position: 'top' })
    }
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
    avatar: profileUser.value.avatar || null,
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
.capitalize { text-transform: capitalize; }

.profile-page {
  display: flex; justify-content: center; align-items: flex-start;
  background: #faf8fc;
}

.pf-card {
  width: 100%; max-width: 620px;
  border: 1px solid #ece6f0; border-radius: 18px; overflow: hidden;
  box-shadow: 0 2px 14px rgba(43, 27, 51, 0.06);
}

/* A band of colour so the card starts with something other than white space. */
.pf-banner {
  height: 96px;
  background: linear-gradient(120deg, #4a148c, #7b1fa2 55%, #9c4dcc);
}
.pf-banner-tools {
  display: flex; justify-content: flex-end; gap: 4px;
  padding: 6px 8px; color: #fff;
}
.pf-banner-tools :deep(.q-btn) { color: #fff; }

.pf-section { margin-top: 16px; }
.pf-label {
  font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase;
  color: #a99bb2; margin-bottom: 7px;
}
.pf-chips { display: flex; flex-wrap: wrap; gap: 6px; justify-content: center; margin-top: 14px; }
.pf-chips--left { justify-content: flex-start; margin-top: 0; }
.pf-chip {
  padding: 4px 11px; border-radius: 999px;
  border: 1px solid #e5dced; background: #fff;
  font-size: 12px; font-weight: 500; color: #6b5a75;
}
.pf-chip--solid {
  background: linear-gradient(135deg, #7b1fa2, #4a148c);
  border-color: transparent; color: #fff;
}
.pf-since {
  display: flex; align-items: center; justify-content: center; gap: 5px;
  font-size: 11.5px; color: #a99bb2; padding-bottom: 4px;
}

.pf-identity { padding-top: 0; margin-top: -62px; }
.pf-avatar {
  background: linear-gradient(135deg, #7b1fa2, #4a148c);
  color: #fff; font-size: 40px; font-weight: 700;
  border: 4px solid #fff; box-shadow: 0 3px 12px rgba(43, 27, 51, 0.18);
}

.pf-name-line {
  display: flex; align-items: center; justify-content: center; gap: 7px;
  margin-top: 12px; flex-wrap: wrap;
}
.pf-name {
  font-size: 23px; font-weight: 700; line-height: 1.2;
  background: linear-gradient(135deg, #4a148c, #7b1fa2);
  -webkit-background-clip: text; background-clip: text; color: transparent;
}
.pf-friend-tag {
  padding: 2px 9px; border-radius: 999px;
  background: #f3ecf7; color: #6a3f86;
  font-size: 10.5px; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase;
}
.pf-city {
  display: flex; align-items: center; justify-content: center; gap: 4px;
  margin-top: 4px; font-size: 13px; color: #7a6a82;
}

.pf-stats {
  display: flex; align-items: stretch; justify-content: center;
  margin: 16px auto 0; max-width: 380px;
  border: 1px solid #ece6f0; border-radius: 14px; background: #fcfafd;
  overflow: hidden;
}
.pf-stat {
  flex: 1; display: flex; flex-direction: column; align-items: center; gap: 1px;
  padding: 10px 6px; border: 0; background: none; cursor: pointer;
  transition: background 0.15s ease;
}
.pf-stat:hover { background: #f3ecf7; }
.pf-stat-n { font-size: 17px; font-weight: 700; color: #2b1b33; line-height: 1.1; }
.pf-stat-l { font-size: 11px; color: #9b8aa5; }
.pf-stat-div { width: 1px; background: #ece6f0; margin: 8px 0; }
</style>
