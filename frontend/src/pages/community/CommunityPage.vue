<template>
  <q-page class="feed-page">
    <div class="feed-shell">
      <header class="feed-head">
        <div class="feed-head-text">
          <span class="page-eyebrow"><q-icon name="forum" size="12px" />Social</span>
          <h1 class="page-title page-title--sm">Community</h1>
        </div>

        <button
          v-if="authStore.isLoggedIn"
          type="button"
          class="compose-fab"
          aria-label="New post"
          @click="composerOpen = true"
        >
          <q-icon name="add" size="24px" />
          <q-tooltip anchor="bottom middle" self="top middle">New post</q-tooltip>
        </button>
      </header>

      <!-- Deep-linked single post (from a shared card) -->
      <div v-if="focusedId" class="focus-bar">
        <q-btn flat dense no-caps color="primary" icon="arrow_back" label="Back to feed" @click="clearFocus" />
        <span class="focus-label">Viewing a single post</span>
      </div>

      <div v-if="focusedId" class="focus-scroll">
        <div v-if="focusLoading" class="row justify-center q-py-xl">
          <q-spinner-dots color="primary" size="30px" />
        </div>
        <div v-else-if="!focusedPost" class="empty-block">
          <q-icon name="search_off" size="46px" />
          <div class="empty-title">Post not found</div>
          <p class="empty-text">It may have been deleted or is no longer available.</p>
          <q-btn flat no-caps color="primary" label="Back to feed" @click="clearFocus" />
        </div>
        <PostCard
          v-else
          :post="focusedPost"
          :comments="store.comments[focusedPost.id] || []"
          :show-comments="true"
          :loading-comments="loadingComments === focusedPost.id"
          @like="onLike"
          @toggle-comments="onToggleComments"
          @comment="onComment"
          @delete="onDelete"
          @report="onReport"
          @delete-comment="onDeleteComment"
          @message-author="onMessageAuthor"
          @share="onShare"
        />
      </div>

      <template v-else>
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

      <div v-else ref="scroller" class="feed-scroll" @scroll.passive="onScroll">
        <PostCard
          v-for="post in store.posts"
          :key="post.id"
          class="snap-item"
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
          @share="onShare"
        />

        <div v-if="store.loadingMore" class="row justify-center q-py-md">
          <q-spinner-dots color="primary" size="28px" />
        </div>
        <div v-else-if="!store.hasMore" class="feed-end">You're all caught up.</div>
      </div>
      </template>
    </div>

    <!-- Compose in a modal so the feed keeps the full page height -->
    <q-dialog v-model="composerOpen" class="composer-dialog" transition-show="jump-up" transition-hide="jump-down">
      <q-card class="composer-card">
        <PostComposer
          dialog
          :destinations="allDestinations"
          :is-agency="isAgency"
          @created="onCreated"
          @close="composerOpen = false"
        />
      </q-card>
    </q-dialog>

    <SharePostDialog v-model="shareDialog" :post="shareTarget" />

    <ReportDialog
      v-if="reportTarget"
      v-model="reportDialog"
      :reported-id="reportTarget.id"
      reported-type="post"
    />
  </q-page>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { useQuasar } from 'quasar'
import { api } from 'src/boot/axios'
import { useAuthStore } from 'src/stores/authStore'
import { useCommunityStore } from 'src/stores/communityStore'
import { useFeedAudioStore } from 'src/stores/feedAudioStore'
import PostCard from 'components/PostCard.vue'
import PostComposer from 'components/PostComposer.vue'
import ReportDialog from 'components/ReportDialog.vue'
import SharePostDialog from 'components/SharePostDialog.vue'
import { POST_TYPES } from 'src/utils/postTypes'
import { useSocialStore } from 'src/stores/socialStore'
import { useRouter, useRoute } from 'vue-router'

const $q = useQuasar()
const authStore = useAuthStore()
const store = useCommunityStore()
const audioStore = useFeedAudioStore()
const socialStore = useSocialStore()
const router = useRouter()
const route = useRoute()

const isAgency = computed(() => authStore.user?.type === 'agency')

const composerOpen = ref(false)

const onCreated = async (post) => {
  store.posts.unshift(post)
  composerOpen.value = false
  // Wait for the new card to render, otherwise the scroll fires against the
  // old height and snap settles part-way into the post.
  await nextTick()
  scroller.value?.scrollTo({ top: 0, behavior: 'smooth' })
}

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
const focusedId = ref(route.query.post ? Number(route.query.post) : null)
const focusedPost = ref(null)
const focusLoading = ref(false)

// A shared card links to ?post=<id> — load just that post and pin it.
const loadFocused = async (id) => {
  if (!id) {
    focusedPost.value = null
    return
  }
  focusLoading.value = true
  try {
    focusedPost.value = await store.fetchPost(id)
    if (!store.comments[id]) await store.fetchComments(id)
  } catch {
    focusedPost.value = null
  } finally {
    focusLoading.value = false
  }
}

const clearFocus = () => {
  focusedId.value = null
  focusedPost.value = null
  router.replace({ query: {} })
}

const shareDialog = ref(false)
const shareTarget = ref(null)
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

const onComment = async (post, payload) => {
  try {
    await store.addComment(post, payload)
  } catch (err) {
    $q.notify({
      color: 'negative',
      position: 'top',
      message: err.response?.data?.errors?.body?.[0]
            || err.response?.data?.message
            || 'Could not add comment',
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

const onShare = (post) => {
  if (!authStore.isLoggedIn) {
    $q.notify({ color: 'info', message: 'Log in to send posts', position: 'top' })
    return
  }
  shareTarget.value = post
  shareDialog.value = true
}

const onReport = (post) => {
  reportTarget.value = post
  reportDialog.value = true
}

// Infinite scroll inside the snap container (the window no longer scrolls here).
const scroller = ref(null)

const onScroll = () => {
  const el = scroller.value
  if (!el) return
  if (el.scrollTop + el.clientHeight >= el.scrollHeight - 700) {
    store.fetchMore(feedFilters.value)
  }
}

watch(() => route.query.post, (id) => {
  focusedId.value = id ? Number(id) : null
  loadFocused(focusedId.value)
})

onMounted(async () => {
  if (focusedId.value) loadFocused(focusedId.value)
  await store.fetchFeed()
  try {
    const r = await api.get('/api/v1/destinations', { params: { per_page: 100 } })
    allDestinations.value = r.data.data || []
  } catch {
    allDestinations.value = []
  }
})

onUnmounted(() => audioStore.stop())
</script>

<style scoped>
.feed-page {
  background: #faf8fc;
  padding: 14px 16px 0;
  /* Fixed height so the feed below can own its own scrolling. */
  height: calc(100vh - 58px);
  display: flex;
  flex-direction: column;
}
.feed-shell {
  max-width: 680px; width: 100%; margin: 0 auto;
  flex: 1; min-height: 0; display: flex; flex-direction: column;
}

/* Discrete, post-by-post scrolling like Reels — one post settles at a time
   instead of the feed drifting between two. */
.feed-scroll {
  flex: 1; min-height: 0;
  overflow-y: auto;
  scroll-snap-type: y mandatory;
  scroll-behavior: smooth;
  scrollbar-width: none;
  padding-bottom: 40vh;   /* lets the last post snap to the top */
}
.feed-scroll::-webkit-scrollbar { display: none; }

.snap-item {
  scroll-snap-align: start;
  /* Stops a fast flick skipping several posts at once. */
  scroll-snap-stop: always;
  margin-bottom: 16px;
}

.feed-head {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  margin-bottom: 10px; flex-shrink: 0;
}
.feed-head-text { display: flex; flex-direction: column; }

/* Compose button — replaces the always-on composer, freeing the space above
   the feed. */
.compose-fab {
  display: inline-flex; align-items: center; justify-content: center;
  width: 42px; height: 42px; flex-shrink: 0;
  border: 0; border-radius: 50%; cursor: pointer; color: #fff;
  background: linear-gradient(135deg, #7b1fa2 0%, #4a148c 100%);
  box-shadow: 0 3px 10px rgba(74, 20, 140, 0.32);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.compose-fab:hover { transform: translateY(-1px) scale(1.04); box-shadow: 0 6px 18px rgba(74, 20, 140, 0.4); }
.compose-fab:active { transform: scale(0.95); }

.composer-card { width: 620px; max-width: 94vw; border-radius: 16px; overflow: hidden; }
.focus-bar {
  display: flex; align-items: center; gap: 10px; margin-bottom: 14px;
  padding: 6px 10px; border-radius: 999px; background: #f3ecf7; border: 1px solid #e8dcf0;
}
.focus-label { font-size: 12px; color: #7a6a82; }

/* One post can easily be taller than the viewport, so this pane scrolls
   normally — snapping only makes sense when there's a list to snap through. */
.focus-scroll {
  flex: 1; min-height: 0; overflow-y: auto;
  padding-bottom: 24px; scrollbar-width: none;
}
.focus-scroll::-webkit-scrollbar { display: none; }

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
  display: flex; gap: 7px; margin-bottom: 12px; flex-shrink: 0;
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
