<template>
  <q-page padding>
    <div class="row items-center q-mb-lg">
      <q-btn flat round icon="arrow_back" color="grey-7" @click="$router.push('/profile')" />
      <div class="text-h5 text-weight-bold q-ml-sm">Privacy Settings</div>
    </div>

    <!-- DM Privacy -->
    <q-card flat bordered class="q-mb-lg">
      <q-card-section>
        <div class="text-subtitle1 text-weight-bold q-mb-xs">Who can message you?</div>
        <div class="text-caption text-grey-6 q-mb-md">
          Control who can start a direct conversation with you.
        </div>

        <q-option-group
          v-model="dmPrivacy"
          :options="dmOptions"
          color="deep-purple"
        />

        <q-btn
          unelevated
          color="deep-purple"
          label="Save"
          class="q-mt-md"
          :loading="saving"
          @click="saveDmPrivacy"
        />
      </q-card-section>
    </q-card>

    <!-- Blocked Users -->
    <q-card flat bordered class="q-mb-lg">
      <q-card-section>
        <div class="text-subtitle1 text-weight-bold q-mb-xs">Blocked Users</div>
        <div class="text-caption text-grey-6 q-mb-md">
          Blocked users cannot see your trips and their messages are hidden from you.
        </div>

        <div v-if="safetyStore.loading" class="q-py-md">
          <q-skeleton v-for="n in 3" :key="n" type="rect" height="56px" class="q-mb-sm" />
        </div>

        <div v-else-if="safetyStore.blockedUsers.length === 0" class="text-center q-py-xl text-grey-5">
          <q-icon name="people_outline" size="3em" />
          <div class="text-body2 q-mt-sm">You haven't blocked anyone</div>
        </div>

        <q-list v-else separator>
          <q-item v-for="user in safetyStore.blockedUsers" :key="user.id">
            <q-item-section avatar>
              <q-avatar size="40px" color="grey-3" text-color="grey-8">
                <img v-if="user.avatar" :src="user.avatar" />
                <span v-else class="text-weight-bold">{{ user.name?.[0]?.toUpperCase() }}</span>
              </q-avatar>
            </q-item-section>
            <q-item-section>
              <q-item-label>{{ user.name }}</q-item-label>
            </q-item-section>
            <q-item-section side>
              <q-btn
                outline
                color="primary"
                label="Unblock"
                size="sm"
                :loading="unblocking === user.id"
                @click="unblock(user)"
              />
            </q-item-section>
          </q-item>
        </q-list>
      </q-card-section>
    </q-card>
  </q-page>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useQuasar } from 'quasar'
import { useAuthStore } from 'src/stores/authStore'
import { useSafetyStore } from 'src/stores/safetyStore'

const $q = useQuasar()
const authStore = useAuthStore()
const safetyStore = useSafetyStore()

const dmPrivacy = ref('everyone')
const saving = ref(false)
const unblocking = ref(null)

const dmOptions = [
  { label: 'Everyone', value: 'everyone' },
  { label: 'People I follow', value: 'followers' },
  { label: 'Nobody', value: 'nobody' },
]

onMounted(async () => {
  safetyStore.fetchBlockList()
  dmPrivacy.value = authStore.user?.dm_privacy ?? 'everyone'
})

const saveDmPrivacy = async () => {
  saving.value = true
  try {
    await authStore.updateProfile({
      name: authStore.user.name,
      dm_privacy: dmPrivacy.value,
    })
    $q.notify({ type: 'positive', message: 'Privacy settings saved', position: 'top' })
  } catch {
    $q.notify({ type: 'negative', message: 'Failed to save', position: 'top' })
  } finally {
    saving.value = false
  }
}

const unblock = async (user) => {
  unblocking.value = user.id
  try {
    await safetyStore.toggleBlock(user.id)
    $q.notify({ color: 'positive', icon: 'check_circle', message: `${user.name} unblocked` })
  } catch {
    $q.notify({ color: 'negative', message: 'Failed to unblock' })
  } finally {
    unblocking.value = null
  }
}
</script>
