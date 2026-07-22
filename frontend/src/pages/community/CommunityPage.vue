<template>
  <q-page class="feed-page">
    <div class="feed-shell">
      <header class="feed-head">
        <div>
          <h1 class="feed-title">Community</h1>
          <p class="feed-sub">Stories, tips and reviews from travellers across Pakistan.</p>
        </div>
      </header>

      <PostComposer
        v-if="authStore.isLoggedIn"
        :destinations="allDestinations"
        :is-agency="isAgency"
        @created="onCreated"
      />

      <!-- Categories -->
      <nav class="filter-row">
        <button
          type="button"
          class="filter-btn"
          :class="{ 'filter-btn--active': activeType === null }"
          @click="setType(null)"
        >All</button>
        <button
          v-for="t in POST_TYPES"
          :key="t.value"
          type="button"
          class="filter-btn"
          :class="{ 'filter-btn--active': activeType === t.value }"
          @click="setType(t.value)"
        >
          <q-icon :name="t.icon" size="14px" />{{ t.short }}
        </button>
      </nav>

      <!-- Feed -->
      <div v-if="store.loading" class="column q-gutter-md">
        <q-card v-for="n in 3" :key="n" flat bordered class="q-pa-md">
          <div class="row items-center q-gutter-md">
            <q-skeleton type="QAvatar" size="40px" />
            <div class="col"><q-skeleton type="text" width="35%" /><q-skeleton type="text" width="20%" /></div>
          </div>
          <q-skeleton type="text" class="q-mt-md" />
          <q-skeleton type="text" width="70%" />
        </q-card>
      </div>

      <div v-else-if="!store.posts.length" class="empty-block">
        <q-icon name="forum" size="46px" />
        <div class="empty-title">Nothing here yet</div>
        <p class="empty-text">
          {{ authStore.isLoggedIn
            ? 'Be the first to share a story, tip or review.'
            : 'Log in to share your travel stories with the community.' }}
        </p>
      </div>

      <div v-else class="column q-gutter-md">
        <PostCard
          v-for="post in store.posts"
          :key="post.id"
          :post="post"
          :comments="store.comments[post.id] || []"
          :show-comments="openComments.has(post.id)"
          :loading-comments="loadingComments === post.id"
          @like="onLike"
          @toggle-comments="onToggleComments"
          @comment="onComment"
          @delete="onDelete"
          @report="onReport"
          @delete-comment="onDeleteComment"
          @message-author="onMessageAuthor"
        />

        <div v-if="store.loadingMore" class="row justify-center q-py-md">
          <q-spinner-dots color="primary" size="28px" />
        </div>
        <div v-else-if="!store.hasMore" class="feed-end">You're all caught up.</div>
      </div>
    </div>

    <ReportDialog
      v-if="reportTarget"
      v-model="reportDialog"
      :reported-id="reportTarget.id"
      reported-type="post"
    />
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useQuasar } from 'quasar'
import { api } from 'src/boot/axios'
import { useAuthStore } from 'src/stores/authStore'
import { useCommunityStore } from 'src/stores/communityStore'
import PostCard from 'components/PostCard.vue'
import PostComposer from 'components/PostComposer.vue'
import ReportDialog from 'components/ReportDialog.vue'
import { POST_TYPES } from 'src/utils/postTypes'
import { useSocialStore } from 'src/stores/socialStore'
import { useRouter } from 'vue-router'

const $q = useQuasar()
const authStore = useAuthStore()
const store = useCommunityStore()
const socialStore = useSocialStore()
const router = useRouter()

const isAgency = computed(() => authStore.user?.type === 'agency')

const onCreated = (post) => store.posts.unshift(post)

// "I'm interested" on a companion post opens a DM with the author.
const onMessageAuthor = async (post) => {
  try {
    const conv = await socialStore.startConversation(post.author.id)
    router.push(`/messages/${conv.id}`)
  } catch (err) {
    const data = err.response?.data
    $q.notify({
      type: data?.requested ? 'info' : 'negative',
      message: data?.message || 'Could not open the conversation',
      position: 'top',
    })
  }
}

const allDestinations = ref([])
const activeType = ref(null)
const openComments = ref(new Set())
const loadingComments = ref(null)
const reportDialog = ref(false)
const reportTarget = ref(null)

const feedFilters = computed(() => (activeType.value ? { type: activeType.value } : {}))

const setType = (value) => {
  activeType.value = value
  store.fetchFeed(feedFilters.value)
}

const onLike = async (post) => {
  if (!authStore.isLoggedIn) {
    $q.notify({ color: 'info', message: 'Log in to like posts', position: 'top' })
    return
  }
  try {
    await store.toggleLike(post)
  } catch {
    $q.notify({ color: 'negative', message: 'Could not update like', position: 'top' })
  }
}

const onToggleComments = async (post) => {
  const set = new Set(openComments.value)
  if (set.has(post.id)) {
    set.delete(post.id)
    openComments.value = set
    return
  }
  set.add(post.id)
  openComments.value = set

  if (!store.comments[post.id]) {
    loadingComments.value = post.id
    try {
      await store.fetchComments(post.id)
    } finally {
      loadingComments.value = null
    }
  }
}

const onComment = async (post, body) => {
  try {
    await store.addComment(post, body)
  } catch (err) {
    $q.notify({
      color: 'negative',
      position: 'top',
      message: err.response?.data?.errors?.body?.[0] || 'Could not add comment',
    })
  }
}

const onDeleteComment = async (post, commentId) => {
  try {
    await store.deleteComment(post, commentId)
  } catch {
    $q.notify({ color: 'negative', message: 'Could not delete comment', position: 'top' })
  }
}

const onDelete = (post) => {
  $q.dialog({
    title: 'Delete post?',
    message: 'This will remove it from the community feed.',
    cancel: true,
    persistent: true,
    ok: { label: 'Delete', color: 'negative', unelevated: true, noCaps: true },
  }).onOk(async () => {
    try {
      await store.deletePost(post.id)
      $q.notify({ color: 'positive', message: 'Post deleted', position: 'top' })
    } catch {
      $q.notify({ color: 'negative', message: 'Could not delete post', position: 'top' })
    }
  })
}

const onReport = (post) => {
  reportTarget.value = post
  reportDialog.value = true
}

// Infinite scroll: load the next page as the bottom of the feed approaches.
const onScroll = () => {
  const nearBottom = window.innerHeight + window.scrollY >= document.body.offsetHeight - 600
  if (nearBottom) store.fetchMore(feedFilters.value)
}

onMounted(async () => {
  window.addEventListener('scroll', onScroll, { passive: true })
  await store.fetchFeed()
  try {
    const r = await api.get('/api/v1/destinations', { params: { per_page: 100 } })
    allDestinations.value = r.data.data || []
  } catch {
    allDestinations.value = []
  }
})

onUnmounted(() => window.removeEventListener('scroll', onScroll))
</script>

<style scoped>
.feed-page { background: #faf8fc; padding: 24px 16px 64px; }
.feed-shell { max-width: 680px; margin: 0 auto; }

.feed-head { margin-bottom: 18px; }
.feed-title { margin: 0; font-size: 28px; font-weight: 700; letter-spacing: -0.02em; color: #2b1b33; }
.feed-sub { margin: 4px 0 0; font-size: 14px; color: #7a6a82; }

/* Composer */
.composer {
  background: #fff; border: 1px solid #ece6f0; border-radius: 14px;
  padding: 14px 16px; margin-bottom: 16px;
}
.composer-top { display: flex; align-items: flex-start; gap: 11px; }
.composer-avatar {
  background: linear-gradient(135deg, #7b1fa2, #4a148c);
  color: #fff; font-weight: 700; font-size: 15px; flex-shrink: 0;
}
.composer-actions {
  display: flex; align-items: center; justify-content: flex-end; gap: 10px; margin-top: 12px;
}
.char-count { margin-right: auto; font-size: 11.5px; color: #b0a3b8; }

.expand-enter-active, .expand-leave-active { transition: opacity 0.18s ease; }
.expand-enter-from, .expand-leave-to { opacity: 0; }

/* Filters */
.filter-row {
  display: flex; gap: 7px; margin-bottom: 16px;
  overflow-x: auto; padding-bottom: 4px; scrollbar-width: none;
}
.filter-row::-webkit-scrollbar { display: none; }
.filter-btn {
  display: inline-flex; align-items: center; gap: 5px; white-space: nowrap;
  padding: 6px 13px; border: 1px solid #e5dced; border-radius: 999px; background: #fff;
  font: inherit; font-size: 12.5px; color: #6b5a75; cursor: pointer; transition: all 0.15s ease;
}
.filter-btn:hover { border-color: #c9b3d6; }
.filter-btn--active { background: var(--q-primary); border-color: var(--q-primary); color: #fff; font-weight: 500; }

.empty-block {
  display: flex; flex-direction: column; align-items: center; text-align: center;
  background: #fff; border: 1px solid #ece6f0; border-radius: 14px; padding: 48px 24px; color: #b0a3b8;
}
.empty-title { font-size: 16px; font-weight: 600; color: #2b1b33; margin-top: 12px; }
.empty-text { font-size: 13px; color: #8a7a92; max-width: 340px; margin: 6px 0 0; line-height: 1.5; }

.feed-end { text-align: center; font-size: 12.5px; color: #b0a3b8; padding: 18px 0 4px; }

@media (max-width: 599px) {
  .feed-page { padding: 16px 12px 48px; }
  .feed-title { font-size: 23px; }
}
</style>
