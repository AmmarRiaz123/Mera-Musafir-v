<template>
  <q-page padding>
    <div class="q-mb-lg">
      <span class="page-eyebrow"><q-icon name="people" size="12px" />Social</span>
      <h1 class="page-title">Discover People</h1>
    </div>

    <!-- Filters -->
    <div class="row q-col-gutter-md q-mb-lg">
      <div class="col-12 col-md-4">
        <q-input
          v-model="search"
          outlined
          dense
          debounce="400"
          placeholder="Search by name or city..."
          clearable
          @update:model-value="load"
        >
          <template #append><q-icon name="search" /></template>
        </q-input>
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <q-input
          v-model="filterCity"
          outlined
          dense
          debounce="400"
          placeholder="Filter by city"
          clearable
          @update:model-value="load"
        />
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <q-select
          v-model="filterGender"
          :options="genderOptions"
          outlined
          dense
          label="Gender"
          clearable
          emit-value
          map-options
          @update:model-value="load"
        />
      </div>
    </div>

    <!-- Loading -->
    <div v-if="socialStore.usersLoading" class="row q-col-gutter-md">
      <div v-for="n in 8" :key="n" class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered>
          <q-card-section class="column items-center q-py-lg">
            <q-skeleton type="QAvatar" size="72px" />
            <q-skeleton type="text" class="q-mt-sm" width="80px" />
            <q-skeleton type="text" width="60px" />
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="socialStore.users.length === 0" class="text-center q-py-xl">
      <q-icon name="people_outline" size="64px" color="grey-5" />
      <div class="text-h6 text-grey-7 q-mt-md">No travelers found</div>
    </div>

    <!-- User cards -->
    <div v-else class="row q-col-gutter-md">
      <div
        v-for="user in socialStore.users"
        :key="user.id"
        class="col-12 col-sm-6 col-md-3"
      >
        <q-card flat bordered class="text-center q-pa-md person-card">
          <q-avatar size="72px" class="q-mb-sm cursor-pointer" @click="$router.push(`/profile/${user.id}`)">
            <img v-if="user.avatar" :src="user.avatar" />
            <q-icon v-else name="person" size="36px" color="grey-5" />
          </q-avatar>

          <div class="row justify-center items-center q-gutter-xs q-mb-xs">
            <span
              class="text-subtitle1 text-weight-bold cursor-pointer"
              @click="$router.push(`/profile/${user.id}`)"
            >{{ user.name }}</span>
            <q-icon v-if="user.is_verified" name="verified" color="deep-purple" size="16px">
              <q-tooltip>Verified User</q-tooltip>
            </q-icon>
            <q-badge v-if="user.is_friend" color="deep-purple" label="Friends" class="q-ml-xs" />
          </div>

          <div v-if="user.city" class="text-caption text-grey-6 q-mb-xs">
            <q-icon name="place" size="xs" /> {{ user.city }}
          </div>

          <div class="row justify-center q-gutter-md q-mb-md text-caption text-grey-7">
            <div><span class="text-weight-bold text-dark">{{ user.followers_count }}</span> followers</div>
            <div><span class="text-weight-bold text-dark">{{ user.following_count }}</span> following</div>
          </div>

          <div class="row q-col-gutter-sm justify-center">
            <div class="col-auto">
              <q-btn
                :label="user.is_following ? 'Unfollow' : 'Follow'"
                :color="user.is_following ? 'grey-7' : 'primary'"
                :outline="user.is_following"
                unelevated
                dense
                rounded
                :loading="followingId === user.id"
                @click="handleFollow(user)"
              />
            </div>
            <div class="col-auto">
              <q-btn
                icon="chat"
                color="deep-purple"
                flat
                dense
                round
                @click="handleMessage(user)"
              >
                <q-tooltip>Message</q-tooltip>
              </q-btn>
            </div>
          </div>
        </q-card>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="!socialStore.usersLoading && socialStore.usersMeta.last_page > 1" class="row justify-center q-mt-xl q-mb-lg">
      <q-pagination
        v-model="currentPage"
        :max="socialStore.usersMeta.last_page"
        color="primary"
        @update:model-value="onPageChange"
        boundary-links
      />
    </div>
  </q-page>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useSocialStore } from 'src/stores/socialStore'
import { useNotificationStore } from 'src/stores/notificationStore'

const router = useRouter()
const $q = useQuasar()
const socialStore = useSocialStore()
const notificationStore = useNotificationStore()

const search = ref('')
const filterCity = ref('')
const filterGender = ref(null)
const currentPage = ref(1)
const followingId = ref(null)

const genderOptions = [
  { label: 'Male', value: 'male' },
  { label: 'Female', value: 'female' },
  { label: 'Other', value: 'other' },
]

const load = () => {
  currentPage.value = 1
  socialStore.fetchUsers({
    search: search.value || undefined,
    city: filterCity.value || undefined,
    gender: filterGender.value || undefined,
    page: 1,
  })
}

const onPageChange = (val) => {
  socialStore.fetchUsers({
    search: search.value || undefined,
    city: filterCity.value || undefined,
    gender: filterGender.value || undefined,
    page: val,
  })
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const handleFollow = async (user) => {
  followingId.value = user.id
  try {
    const res = await socialStore.followUser(user.id)
    $q.notify({ type: 'positive', message: res.message, position: 'top' })
  } catch {
    $q.notify({ type: 'negative', message: 'Action failed', position: 'top' })
  } finally {
    followingId.value = null
  }
}

const handleMessage = async (user) => {
  try {
    const conv = await socialStore.startConversation(user.id)
    router.push(`/messages/${conv.id}`)
  } catch (e) {
    const data = e.response?.data
    if (data?.requested) {
      notificationStore.promptRequest({ id: user.id, name: user.name, avatar: user.avatar })
    } else {
      $q.notify({ type: 'negative', message: data?.message || 'Cannot start conversation', position: 'top' })
    }
  }
}

onMounted(() => load())
</script>

<style scoped>
.person-card { transition: transform 0.2s, box-shadow 0.2s; }
.person-card:hover { transform: translateY(-4px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
</style>
