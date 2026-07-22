<template>
  <q-dialog
    :model-value="modelValue"
    class="connections-dialog"
    transition-show="jump-up"
    transition-hide="jump-down"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <q-card class="cx-card">
      <header class="cx-head">
        <div class="cx-title">{{ isSelf ? 'Your circle' : `${userName}'s circle` }}</div>
        <q-btn flat round dense size="sm" icon="close" color="grey-7" v-close-popup />
      </header>

      <div class="cx-tabs">
        <button
          v-for="t in tabs"
          :key="t.value"
          type="button"
          class="cx-tab"
          :class="{ 'cx-tab--active': tab === t.value }"
          @click="setTab(t.value)"
        >
          {{ t.label }}
          <span class="cx-tab-n">{{ counts[t.value] ?? '·' }}</span>
        </button>
      </div>

      <div class="cx-body">
        <div v-if="loading" class="cx-state"><q-spinner-dots color="primary" size="28px" /></div>

        <div v-else-if="!people.length" class="cx-state">
          <q-icon :name="emptyState.icon" size="34px" />
          <div class="cx-empty-title">{{ emptyState.title }}</div>
          <div class="cx-empty-text">{{ emptyState.text }}</div>
        </div>

        <div v-else class="cx-list">
          <div v-for="p in people" :key="p.id" class="cx-row" @click="goToProfile(p)">
            <q-avatar size="42px" class="cx-avatar">
              <img v-if="p.avatar" :src="p.avatar" />
              <span v-else>{{ p.name?.[0]?.toUpperCase() }}</span>
            </q-avatar>

            <div class="cx-who">
              <div class="cx-name-line">
                <span class="cx-name">{{ p.name }}</span>
                <q-icon v-if="p.is_verified" name="verified" size="14px" color="deep-purple" />
                <span v-if="p.is_agency" class="cx-agency">Agency</span>
              </div>
              <div class="cx-sub">
                <template v-if="p.city"><q-icon name="place" size="11px" />{{ p.city }}</template>
                <template v-else>Traveller</template>
              </div>
            </div>

            <!-- The row is the link, so the menu must not travel with it -->
            <div class="cx-actions" @click.stop>
              <q-spinner v-if="acting === p.id" color="primary" size="19px" />
              <q-btn v-else flat round dense size="sm" icon="more_horiz" color="grey-6">
                <q-menu auto-close anchor="bottom right" self="top right">
                  <q-list style="min-width: 178px">
                    <q-item v-if="isSelf && tab !== 'following'" clickable @click="confirmRemove(p)">
                      <q-item-section avatar><q-icon name="group_remove" size="xs" /></q-item-section>
                      <q-item-section>Remove follower</q-item-section>
                    </q-item>
                    <q-item v-if="isSelf && tab !== 'followers'" clickable @click="unfollow(p)">
                      <q-item-section avatar><q-icon name="person_remove" size="xs" /></q-item-section>
                      <q-item-section>Unfollow</q-item-section>
                    </q-item>
                    <q-item clickable @click="confirmBlock(p)">
                      <q-item-section avatar><q-icon name="block" size="xs" color="negative" /></q-item-section>
                      <q-item-section class="text-negative">Block</q-item-section>
                    </q-item>
                  </q-list>
                </q-menu>
              </q-btn>
            </div>
          </div>
        </div>
      </div>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { api } from 'src/boot/axios'
import { useSafetyStore } from 'src/stores/safetyStore'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  userId:     { type: Number, required: true },
  userName:   { type: String, default: '' },
  isSelf:     { type: Boolean, default: false },
  initialTab: { type: String, default: 'followers' },
  // Counts the opener already knows, so every tab shows a number immediately
  // rather than a placeholder until you visit it.
  knownCounts: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['update:modelValue', 'changed'])

const $q = useQuasar()
const router = useRouter()
const safetyStore = useSafetyStore()

const tabs = [
  { value: 'followers', label: 'Followers' },
  { value: 'following', label: 'Following' },
  { value: 'friends',   label: 'Friends' },
]

const tab = ref(props.initialTab)
const people = ref([])
const loading = ref(false)
const acting = ref(null)
const counts = ref({})

const emptyState = computed(() => ({
  followers: {
    icon: 'group_add',
    title: props.isSelf ? 'No followers yet' : 'No followers yet',
    text: props.isSelf
      ? 'Post a trip or a story — people find you through what you share.'
      : 'Nobody follows them yet.',
  },
  following: {
    icon: 'travel_explore',
    title: props.isSelf ? "You're not following anyone" : 'Not following anyone',
    text: props.isSelf
      ? 'Browse People to find travellers heading where you want to go.'
      : "They haven't followed anyone yet.",
  },
  friends: {
    icon: 'handshake',
    title: 'No mutuals yet',
    text: 'Friends are people who follow each other back.',
  },
}[tab.value]))

const load = async () => {
  loading.value = true
  try {
    const { data } = await api.get(`/api/v1/users/${props.userId}/connections`, {
      params: { type: tab.value },
    })
    people.value = data.data || []
    counts.value = { ...counts.value, [tab.value]: people.value.length }
  } catch {
    people.value = []
  } finally {
    loading.value = false
  }
}

const setTab = (value) => {
  tab.value = value
  load()
}

// Only fetch when actually opened, and reset to the tab that was clicked.
watch(() => props.modelValue, (open) => {
  if (!open) return
  tab.value = props.initialTab
  counts.value = { ...props.knownCounts }
  load()
})

const goToProfile = (p) => {
  emit('update:modelValue', false)
  router.push(`/profile/${p.id}`)
}

const confirmRemove = (p) => {
  $q.dialog({
    title: 'Remove follower',
    message: `${p.name} will stop following you. They can follow again — blocking is what stops that.`,
    cancel: { flat: true, noCaps: true, color: 'grey-7', label: 'Keep' },
    ok: { unelevated: true, rounded: true, noCaps: true, color: 'negative', label: 'Remove' },
  }).onOk(async () => {
    acting.value = p.id
    try {
      const { data } = await api.delete(`/api/v1/users/${p.id}/follower`)
      people.value = people.value.filter((x) => x.id !== p.id)
      counts.value = { ...counts.value, [tab.value]: people.value.length }
      $q.notify({ color: 'positive', icon: 'check_circle', message: data.message, position: 'top' })
      emit('changed')
    } catch {
      $q.notify({ color: 'negative', message: 'Could not remove them just now.', position: 'top' })
    } finally {
      acting.value = null
    }
  })
}

const unfollow = async (p) => {
  acting.value = p.id
  try {
    await api.post(`/api/v1/users/${p.id}/follow`)
    people.value = people.value.filter((x) => x.id !== p.id)
    counts.value = { ...counts.value, [tab.value]: people.value.length }
    $q.notify({ color: 'positive', icon: 'check_circle', message: `Unfollowed ${p.name}`, position: 'top' })
    emit('changed')
  } catch {
    $q.notify({ color: 'negative', message: 'Could not unfollow them just now.', position: 'top' })
  } finally {
    acting.value = null
  }
}

const confirmBlock = (p) => {
  $q.dialog({
    title: `Block ${p.name}?`,
    message: "They won't see your trips or reach you, and you won't see theirs. You can undo this in Privacy Settings.",
    cancel: { flat: true, noCaps: true, color: 'grey-7', label: 'Cancel' },
    ok: { unelevated: true, rounded: true, noCaps: true, color: 'negative', label: 'Block' },
  }).onOk(async () => {
    acting.value = p.id
    try {
      await safetyStore.toggleBlock(p.id)
      people.value = people.value.filter((x) => x.id !== p.id)
      counts.value = { ...counts.value, [tab.value]: people.value.length }
      $q.notify({ color: 'positive', icon: 'check_circle', message: `${p.name} blocked`, position: 'top' })
      emit('changed')
    } catch {
      $q.notify({ color: 'negative', message: 'Could not block them just now.', position: 'top' })
    } finally {
      acting.value = null
    }
  })
}
</script>

<style scoped>
.cx-card { width: 460px; max-width: 94vw; border-radius: 16px; overflow: hidden; }

.cx-head {
  display: flex; align-items: center; gap: 10px;
  padding: 14px 14px 12px; border-bottom: 1px solid #f4eff7;
  background: linear-gradient(180deg, #f7f0fb, #fff);
}
.cx-title {
  flex: 1; font-size: 16px; font-weight: 700;
  background: linear-gradient(135deg, #4a148c, #7b1fa2);
  -webkit-background-clip: text; background-clip: text; color: transparent;
}

.cx-tabs { display: flex; gap: 6px; padding: 10px 14px; border-bottom: 1px solid #f4eff7; }
.cx-tab {
  display: inline-flex; align-items: center; gap: 6px;
  flex: 1; justify-content: center;
  padding: 7px 10px; border-radius: 999px; cursor: pointer;
  border: 1px solid #ece6f0; background: #fff;
  font-size: 12.5px; font-weight: 500; color: #6b5a75;
  transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}
.cx-tab:hover { border-color: #c9b3d6; background: #fcfafd; }
.cx-tab--active {
  background: linear-gradient(135deg, #7b1fa2, #4a148c);
  border-color: transparent; color: #fff; font-weight: 600;
}
.cx-tab-n { font-size: 11px; opacity: 0.75; }

.cx-body { min-height: 220px; max-height: 54vh; overflow-y: auto; }
.cx-state {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 6px; padding: 42px 24px; text-align: center; color: #b0a3b8;
}
.cx-empty-title { font-size: 13.5px; font-weight: 600; color: #6b5a75; }
.cx-empty-text { font-size: 12px; max-width: 30ch; line-height: 1.45; }

.cx-list { padding: 6px 0; }
.cx-row {
  display: flex; align-items: center; gap: 11px;
  padding: 9px 14px; cursor: pointer;
  transition: background 0.14s ease;
}
.cx-row:hover { background: #f7f3fa; }
.cx-avatar {
  flex-shrink: 0;
  background: linear-gradient(135deg, #7b1fa2, #4a148c);
  color: #fff; font-weight: 700; font-size: 15px;
}
.cx-who { flex: 1; min-width: 0; }
.cx-name-line { display: flex; align-items: center; gap: 5px; }
.cx-name {
  font-size: 13.5px; font-weight: 600; color: #2b1b33;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.cx-agency {
  padding: 1px 6px; border-radius: 999px; color: #fff;
  background: linear-gradient(135deg, #7b1fa2, #4a148c);
  font-size: 8.5px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase;
}
.cx-sub {
  display: flex; align-items: center; gap: 3px;
  font-size: 11.5px; color: #9b8aa5; margin-top: 1px;
}

.cx-actions {
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; min-width: 32px; cursor: default;
}
</style>
