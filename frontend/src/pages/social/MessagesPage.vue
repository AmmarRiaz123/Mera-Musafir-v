<template>
  <q-page padding>
    <div class="text-h4 text-weight-bold q-mb-md">Messages</div>

    <q-tabs
      v-model="tab"
      class="text-primary q-mb-md"
      active-color="primary"
      indicator-color="primary"
      align="left"
      narrow-indicator
      no-caps
    >
      <q-tab name="chats" icon="chat" label="Chats" />
      <q-tab name="blocked" icon="block" label="Blocked" />
    </q-tabs>
    <q-separator class="q-mb-md" />

    <q-tab-panels v-model="tab" animated class="bg-transparent">
      <!-- CHATS -->
      <q-tab-panel name="chats" class="q-pa-none">
        <div v-if="socialStore.convsLoading">
          <q-item v-for="n in 5" :key="n" class="q-mb-sm">
            <q-item-section avatar><q-skeleton type="QAvatar" /></q-item-section>
            <q-item-section>
              <q-skeleton type="text" width="40%" />
              <q-skeleton type="text" width="70%" />
            </q-item-section>
          </q-item>
        </div>

        <div v-else-if="chats.length === 0" class="text-center q-py-xl">
          <q-icon name="chat_bubble_outline" size="64px" color="grey-5" />
          <div class="text-h6 text-grey-7 q-mt-md">No conversations yet</div>
          <div class="text-grey-6 q-mb-lg">Find people to chat with</div>
          <q-btn color="primary" label="Discover People" to="/people" unelevated rounded />
        </div>

        <q-list v-else separator bordered class="rounded-borders">
          <q-item
            v-for="conv in chats"
            :key="conv.id"
            clickable
            v-ripple
            @click="$router.push(`/messages/${conv.id}`)"
            :class="{ 'bg-purple-1': conv.unread_count > 0 }"
          >
            <q-item-section avatar>
              <q-avatar size="48px">
                <img v-if="conv.other_user.avatar" :src="conv.other_user.avatar" />
                <q-icon v-else name="person" size="24px" color="grey-5" />
              </q-avatar>
            </q-item-section>

            <q-item-section>
              <q-item-label class="row items-center q-gutter-xs">
                <span :class="{ 'text-weight-bold': conv.unread_count > 0 }">{{ conv.other_user.name }}</span>
                <q-icon v-if="conv.other_user.is_verified" name="verified" color="deep-purple" size="14px" />
              </q-item-label>
              <q-item-label caption lines="1">
                <span v-if="conv.last_message">
                  <span v-if="conv.last_message.is_mine" class="text-grey-6">You: </span>
                  <span v-if="conv.last_message.type === 'trip_invite'">
                    <q-icon name="card_travel" size="xs" /> Trip Invite
                  </span>
                  <span v-else>{{ conv.last_message.body }}</span>
                </span>
                <span v-else class="text-grey-5">No messages yet</span>
              </q-item-label>
            </q-item-section>

            <q-item-section side top>
              <q-item-label caption>{{ formatTime(conv.last_message_at) }}</q-item-label>
              <q-badge v-if="conv.unread_count > 0" color="deep-purple" :label="conv.unread_count" rounded />
            </q-item-section>
          </q-item>
        </q-list>
      </q-tab-panel>

      <!-- BLOCKED -->
      <q-tab-panel name="blocked" class="q-pa-none">
        <div class="text-caption text-grey-6 q-mb-md">
          Conversations with people you've blocked. Manage who's blocked in
          <router-link to="/privacy" class="text-primary">Privacy Settings</router-link>.
        </div>

        <div v-if="socialStore.convsLoading">
          <q-item v-for="n in 3" :key="n" class="q-mb-sm">
            <q-item-section avatar><q-skeleton type="QAvatar" /></q-item-section>
            <q-item-section><q-skeleton type="text" width="50%" /></q-item-section>
          </q-item>
        </div>

        <div v-else-if="blockedChats.length === 0" class="text-center q-py-xl text-grey-5">
          <q-icon name="block" size="64px" color="grey-5" />
          <div class="text-h6 text-grey-7 q-mt-md">No blocked chats</div>
        </div>

        <q-list v-else separator bordered class="rounded-borders">
          <q-item
            v-for="conv in blockedChats"
            :key="conv.id"
            clickable
            v-ripple
            @click="$router.push(`/messages/${conv.id}`)"
          >
            <q-item-section avatar>
              <q-avatar size="48px">
                <img v-if="conv.other_user.avatar" :src="conv.other_user.avatar" />
                <q-icon v-else name="person" size="24px" color="grey-5" />
              </q-avatar>
            </q-item-section>

            <q-item-section>
              <q-item-label class="row items-center q-gutter-xs">
                <span class="text-grey-8">{{ conv.other_user.name }}</span>
                <q-badge color="grey-6" label="Blocked" />
              </q-item-label>
              <q-item-label caption lines="1">
                <span v-if="conv.last_message">
                  <span v-if="conv.last_message.is_mine" class="text-grey-6">You: </span>
                  <span v-if="conv.last_message.type === 'trip_invite'">
                    <q-icon name="card_travel" size="xs" /> Trip Invite
                  </span>
                  <span v-else>{{ conv.last_message.body }}</span>
                </span>
                <span v-else class="text-grey-5">No messages yet</span>
              </q-item-label>
            </q-item-section>

            <q-item-section side top>
              <q-item-label caption>{{ formatTime(conv.last_message_at) }}</q-item-label>
            </q-item-section>
          </q-item>
        </q-list>
      </q-tab-panel>
    </q-tab-panels>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useSocialStore } from 'src/stores/socialStore'
import { useSafetyStore } from 'src/stores/safetyStore'

const socialStore = useSocialStore()
const safetyStore = useSafetyStore()

const tab = ref('chats')

// Set of user ids I've blocked — reactive to the safety store.
const blockedIds = computed(() => new Set(safetyStore.blockedUsers.map((u) => u.id)))

const byRecent = (a, b) => {
  if (!a.last_message_at) return 1
  if (!b.last_message_at) return -1
  return new Date(b.last_message_at) - new Date(a.last_message_at)
}

// Chats = conversations with people I have NOT blocked.
const chats = computed(() =>
  socialStore.conversations.filter((c) => !blockedIds.value.has(c.other_user.id)).sort(byRecent),
)

// Blocked = conversations with people I HAVE blocked (moved here on block).
const blockedChats = computed(() =>
  socialStore.conversations.filter((c) => blockedIds.value.has(c.other_user.id)).sort(byRecent),
)

const formatTime = (ts) => {
  if (!ts) return ''
  const d = new Date(ts)
  const now = new Date()
  const diff = now - d
  if (diff < 86400000) return d.toLocaleTimeString('en-PK', { hour: '2-digit', minute: '2-digit' })
  return d.toLocaleDateString('en-PK', { day: 'numeric', month: 'short' })
}

onMounted(() => {
  socialStore.fetchConversations()
  safetyStore.fetchBlockList()
})
</script>
