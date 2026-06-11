<template>
  <q-page padding>
    <div v-if="tripStore.loading" class="row justify-center q-py-xl">
      <q-spinner-dots color="primary" size="4em" />
    </div>

    <div v-else-if="!trip" class="text-center q-py-xl">
      <q-icon name="error_outline" size="64px" color="negative" />
      <div class="text-h5 q-mt-md">Trip not found</div>
      <q-btn color="primary" label="Back to Trips" to="/trips" class="q-mt-lg" />
    </div>

    <div v-else>
      <q-btn
        flat
        color="primary"
        icon="arrow_back"
        label="Back to Trips"
        @click="$router.push('/trips')"
        class="q-mb-md"
      />

      <q-img
        :src="trip.cover_image || 'https://via.placeholder.com/1200x500?text=No+Image'"
        ratio="2.4"
        class="rounded-borders shadow-2 q-mb-lg bg-grey-3"
      />

      <!-- Header row -->
      <div class="row items-start justify-between q-mb-md">
        <div class="col">
          <div class="text-h4 text-weight-bold">{{ trip.title }}</div>
          <div class="row items-center text-grey-7 q-mt-xs">
            <q-icon name="place" size="sm" class="q-mr-xs" />
            <span class="text-subtitle1">{{ trip.destination?.name }}</span>
          </div>
        </div>
        <div class="q-gutter-sm col-auto">
          <q-badge
            v-if="trip.visibility === 'women_only'"
            color="pink-6"
            class="q-pa-sm text-subtitle2"
          >
            <q-icon name="female" size="xs" class="q-mr-xs" />Women Only
          </q-badge>
          <q-badge color="primary" class="q-pa-sm text-subtitle2 capitalize">{{ trip.type }}</q-badge>
          <q-badge
            :color="statusColor(trip.status)"
            class="q-pa-sm text-subtitle2 capitalize"
          >{{ trip.status }}</q-badge>
        </div>
      </div>

      <!-- Info grid -->
      <div class="row q-col-gutter-md q-mb-lg">
        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered class="q-pa-md text-center">
            <q-icon name="calendar_today" color="primary" size="sm" />
            <div class="text-caption text-grey-7 q-mt-xs">Start Date</div>
            <div class="text-weight-bold">{{ formatDate(trip.start_date) }}</div>
          </q-card>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered class="q-pa-md text-center">
            <q-icon name="event_available" color="primary" size="sm" />
            <div class="text-caption text-grey-7 q-mt-xs">End Date</div>
            <div class="text-weight-bold">{{ formatDate(trip.end_date) }}</div>
          </q-card>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered class="q-pa-md text-center">
            <q-icon name="group" color="primary" size="sm" />
            <div class="text-caption text-grey-7 q-mt-xs">Capacity</div>
            <div class="text-weight-bold">{{ trip.current_count }} / {{ trip.max_travelers }}</div>
          </q-card>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered class="q-pa-md text-center">
            <q-icon name="payments" color="primary" size="sm" />
            <div class="text-caption text-grey-7 q-mt-xs">Budget</div>
            <div class="text-weight-bold text-caption">
              <span v-if="trip.budget_min || trip.budget_max">
                PKR {{ formatBudget(trip.budget_min) }}
                <span v-if="trip.budget_max"> – {{ formatBudget(trip.budget_max) }}</span>
              </span>
              <span v-else>Not specified</span>
            </div>
          </q-card>
        </div>
      </div>

      <!-- Capacity bar -->
      <div class="q-mb-xl">
        <div class="row items-center justify-between text-caption text-grey-7 q-mb-xs">
          <span>{{ trip.spots_left }} spots left</span>
          <span>{{ trip.current_count }}/{{ trip.max_travelers }} joined</span>
        </div>
        <q-linear-progress
          :value="trip.current_count / trip.max_travelers"
          :color="trip.is_full ? 'negative' : 'primary'"
          rounded
          size="10px"
        />
      </div>

      <!-- Description -->
      <div v-if="trip.description" class="q-mb-xl">
        <div class="text-h6 q-mb-sm">About this Trip</div>
        <div class="text-body1 text-grey-9" style="line-height: 1.8;">{{ trip.description }}</div>
      </div>

      <!-- Trip tools (members only) -->
      <div v-if="isHost || isJoined" class="q-mb-lg">
        <div class="text-subtitle2 text-grey-7 q-mb-sm">Trip Tools</div>
        <div class="row q-gutter-sm">
          <q-btn
            unelevated rounded icon="chat" label="Chat" color="primary"
            :to="`/trips/${trip.id}/chat?name=${encodeURIComponent(trip.title)}`"
          />
          <q-btn
            unelevated rounded icon="map" label="Itinerary" color="deep-purple"
            :to="`/trips/${trip.id}/itinerary`"
          />
          <q-btn
            unelevated rounded icon="account_balance_wallet" label="Expenses" color="teal"
            :to="`/trips/${trip.id}/expenses`"
          />
          <q-btn
            unelevated rounded icon="checklist" label="Checklist" color="orange-8"
            :to="`/trips/${trip.id}/checklist`"
          />
          <q-btn
            unelevated rounded icon="person_add" label="Invite" color="secondary"
            @click="openInviteDialog"
          />
        </div>
      </div>

      <!-- Join / Leave action -->
      <q-card flat bordered class="q-mb-xl bg-purple-1">
        <q-card-section class="row items-center justify-between">
          <div>
            <div class="text-subtitle1 text-weight-bold" v-if="isHost">
              <q-icon name="star" color="amber-8" class="q-mr-xs" />You are the host
            </div>
            <div class="text-subtitle1 text-weight-bold text-positive" v-else-if="isJoined">
              <q-icon name="check_circle" color="positive" class="q-mr-xs" />You're in this trip
            </div>
            <div class="text-subtitle1" v-else-if="trip.is_full">
              <q-icon name="block" color="negative" class="q-mr-xs" />This trip is full
            </div>
            <div class="text-subtitle1" v-else>
              Ready to join this adventure?
            </div>
          </div>
          <div>
            <q-btn
              v-if="!isHost && isJoined"
              color="negative"
              outline
              label="Leave Trip"
              icon="exit_to_app"
              @click="confirmLeave"
              :loading="actionLoading"
              rounded
            />
            <q-btn
              v-else-if="!isHost && !isJoined && !trip.is_full"
              color="primary"
              unelevated
              :label="trip.visibility === 'invite_only' ? 'Request to Join' : 'Join Trip'"
              icon="add_circle"
              @click="handleJoin"
              :loading="actionLoading"
              rounded
            />
          </div>
        </q-card-section>
      </q-card>

      <!-- Host info -->
      <div class="q-mb-xl" v-if="trip.creator">
        <div class="text-h6 q-mb-md">Hosted by</div>
        <div class="row items-center">
          <q-avatar size="48px" class="q-mr-md">
            <img v-if="trip.creator.avatar" :src="trip.creator.avatar" />
            <q-icon v-else name="person" />
          </q-avatar>
          <div>
            <div class="text-weight-bold">{{ trip.creator.name }}</div>
            <div class="text-caption text-grey-7" v-if="trip.creator.city">{{ trip.creator.city }}</div>
          </div>
        </div>
      </div>

      <!-- Suggested travelers (host only) -->
      <div v-if="isHost" class="q-mb-xl">
        <div class="text-h6 q-mb-xs">People who might want to join</div>
        <div class="text-caption text-grey-6 q-mb-md">Matched by travel style, region interest, and activity</div>

        <div v-if="matchStore.travelersLoading" class="row q-gutter-sm">
          <q-card v-for="n in 4" :key="n" flat bordered style="width:160px">
            <q-card-section class="text-center">
              <q-skeleton type="QAvatar" size="48px" class="q-mx-auto q-mb-sm" />
              <q-skeleton type="text" width="80%" class="q-mx-auto" />
              <q-skeleton type="text" width="50%" class="q-mx-auto" />
            </q-card-section>
          </q-card>
        </div>

        <div v-else-if="matchStore.suggestedTravelers.length === 0" class="text-grey-5 text-body2">
          No matching travelers found yet.
        </div>

        <div v-else class="row q-col-gutter-sm">
          <div
            v-for="item in matchStore.suggestedTravelers"
            :key="item.user.id"
            class="col-6 col-sm-4 col-md-3"
          >
            <q-card flat bordered class="text-center q-pa-md">
              <q-avatar size="48px" color="deep-purple-1" text-color="deep-purple" class="q-mb-xs">
                <img v-if="item.user.avatar" :src="item.user.avatar" />
                <span v-else class="text-weight-bold">{{ item.user.name?.[0]?.toUpperCase() }}</span>
              </q-avatar>
              <div class="text-weight-bold text-body2 ellipsis">{{ item.user.name }}</div>
              <div class="text-caption text-grey-6" v-if="item.user.city">{{ item.user.city }}</div>
              <q-badge color="deep-purple-1" text-color="deep-purple-9" class="q-mt-xs" style="font-size:10px">
                {{ item.score }}% match
              </q-badge>
              <div class="q-mt-sm">
                <q-btn
                  unelevated rounded dense
                  color="deep-purple"
                  label="Invite"
                  size="sm"
                  @click="inviteUser(item.user)"
                />
              </div>
            </q-card>
          </div>
        </div>
      </div>

      <!-- Members list -->
      <div v-if="trip.members && trip.members.length">
        <div class="text-h6 q-mb-md">
          Members
          <q-badge color="primary" class="q-ml-sm">{{ trip.members.length }}</q-badge>
        </div>
        <div class="row q-col-gutter-sm">
          <div
            v-for="member in trip.members"
            :key="member.id"
            class="col-6 col-sm-4 col-md-3"
          >
            <q-card flat bordered class="q-pa-sm row items-center no-wrap">
              <q-avatar size="36px" class="q-mr-sm">
                <img v-if="member.avatar" :src="member.avatar" />
                <q-icon v-else name="person" />
              </q-avatar>
              <div class="ellipsis">
                <div class="text-caption text-weight-bold ellipsis">{{ member.name }}</div>
                <div class="text-caption text-grey-7 ellipsis" v-if="member.city">{{ member.city }}</div>
              </div>
            </q-card>
          </div>
        </div>
      </div>
    </div>

    <!-- Invite dialog -->
    <q-dialog v-model="inviteDialog">
      <q-card style="min-width: 320px">
        <q-card-section>
          <div class="text-h6">Invite Someone</div>
          <div class="text-caption text-grey-6">Enter the name or share a link (feature coming soon)</div>
        </q-card-section>
        <q-card-section class="q-pt-none">
          <q-input
            v-model="inviteName"
            outlined
            label="Name or username"
            autofocus
            @keyup.enter="sendInvite"
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn unelevated color="secondary" label="Send Invite" @click="sendInvite" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Leave confirm dialog -->
    <q-dialog v-model="leaveDialog">
      <q-card style="min-width: 300px">
        <q-card-section>
          <div class="text-h6">Leave Trip?</div>
        </q-card-section>
        <q-card-section class="q-pt-none">
          Are you sure you want to leave this trip?
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn flat color="negative" label="Leave" @click="doLeave" :loading="actionLoading" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useTripStore } from 'src/stores/tripStore'
import { useAuthStore } from 'src/stores/authStore'
import { useMatchStore } from 'src/stores/matchStore'

const route = useRoute()
const router = useRouter() // eslint-disable-line no-unused-vars
const $q = useQuasar()
const tripStore = useTripStore()
const authStore = useAuthStore()
const matchStore = useMatchStore()

const actionLoading = ref(false)
const leaveDialog = ref(false)
const inviteDialog = ref(false)
const inviteName = ref('')

const trip = computed(() => tripStore.currentTrip)

const isHost = computed(() => {
  if (!trip.value || !authStore.user) return false
  return trip.value.creator?.id === authStore.user.id
})

const isJoined = computed(() => {
  if (!trip.value || !authStore.user) return false
  return trip.value.members?.some(m => m.id === authStore.user.id)
})

onMounted(async () => {
  const id = route.params.id
  if (id) {
    try {
      await tripStore.fetchTrip(id)
      if (isHost.value) {
        matchStore.fetchSuggestedTravelers(id)
      }
    } catch {
      // handled in template via trip === null
    }
  }
})

const handleJoin = async () => {
  actionLoading.value = true
  try {
    const result = await tripStore.joinTrip(trip.value.id)
    $q.notify({ color: 'positive', message: result.message, icon: 'check_circle' })
  } catch (err) {
    const msg = err.response?.data?.message || 'Could not join trip'
    $q.notify({ color: 'negative', message: msg, icon: 'error' })
  } finally {
    actionLoading.value = false
  }
}

const confirmLeave = () => {
  leaveDialog.value = true
}

const doLeave = async () => {
  actionLoading.value = true
  try {
    const result = await tripStore.leaveTrip(trip.value.id)
    leaveDialog.value = false
    $q.notify({ color: 'info', message: result.message, icon: 'info' })
  } catch (err) {
    const msg = err.response?.data?.message || 'Could not leave trip'
    $q.notify({ color: 'negative', message: msg, icon: 'error' })
  } finally {
    actionLoading.value = false
  }
}

const openInviteDialog = () => {
  inviteName.value = ''
  inviteDialog.value = true
}

const sendInvite = () => {
  inviteDialog.value = false
  $q.notify({ color: 'info', message: 'Invite feature coming soon', icon: 'person_add' })
}

const inviteUser = (user) => {
  inviteName.value = user.name
  inviteDialog.value = true
}

const formatDate = (dateStr) => {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return d.toLocaleDateString('en-PK', { day: 'numeric', month: 'short', year: 'numeric' })
}

const formatBudget = (amount) => {
  if (!amount) return '—'
  return Number(amount).toLocaleString()
}

const statusColor = (status) => {
  const map = { planning: 'orange', active: 'positive', completed: 'grey-6', archived: 'grey-4' }
  return map[status] || 'grey'
}
</script>

<style scoped>
.capitalize { text-transform: capitalize; }
.bg-purple-1 { background: #f3e5f5; }
</style>
