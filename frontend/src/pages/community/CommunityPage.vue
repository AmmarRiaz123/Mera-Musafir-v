<template>
  <q-page class="feed-page">
    <div class="feed-shell">
      <header class="feed-head">
        <div>
          <h1 class="feed-title">Community</h1>
          <p class="feed-sub">Stories, tips and reviews from travellers across Pakistan.</p>
        </div>
      </header>

      <!-- Composer -->
      <section v-if="authStore.isLoggedIn" class="composer">
        <div class="composer-top">
          <q-avatar size="38px" class="composer-avatar">
            <img v-if="authStore.user?.avatar" :src="authStore.user.avatar" />
            <span v-else>{{ authStore.user?.name?.[0]?.toUpperCase() }}</span>
          </q-avatar>

          <q-input
            v-model="draft.body"
            class="col"
            outlined
            autogrow
            :rows="expanded ? 3 : 1"
            maxlength="2000"
            :placeholder="placeholder"
            @focus="expanded = true"
          />
        </div>

        <transition name="expand">
          <div v-if="expanded" class="composer-body">
            <div class="row q-col-gutter-sm q-mt-sm">
              <div class="col-12 col-sm-6">
                <q-select
                  v-model="draft.type"
                  :options="typeOptions"
                  option-value="value" option-label="label" emit-value map-options
                  outlined dense label="Post type"
                />
              </div>
              <div class="col-12 col-sm-6">
                <q-select
                  v-model="draft.destination_id"
                  :options="destinations"
                  option-value="id" option-label="name" emit-value map-options
                  outlined dense clearable use-input
                  label="Tag a destination"
                  @filter="filterDestinations"
                >
                  <template v-slot:prepend><q-icon name="place" size="18px" color="primary" /></template>
                </q-select>
              </div>
            </div>

            <ImageUpload
              v-model="draft.image"
              type="destination"
              label="Add a photo"
              class="full-width q-mt-sm"
            />

            <div class="composer-actions">
              <span class="char-count">{{ draft.body.length }} / 2000</span>
              <q-btn flat no-caps color="grey-7" label="Cancel" @click="resetDraft" />
              <q-btn
                unelevated rounded no-caps color="primary" icon="send" label="Post"
                :disable="!draft.body.trim()" :loading="posting"
                @click="submitPost"
              />
            </div>
          </div>
        </transition>
      </section>

      <!-- Filters -->
      <nav class="filter-row">
        <button
          v-for="f in filters"
          :key="f.value ?? 'all'"
          type="button"
          class="filter-btn"
          :class="{ 'filter-btn--active': activeType === f.value }"
          @click="setType(f.value)"
        >{{ f.label }}</button>
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
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { useQuasar } from 'quasar'
import { api } from 'src/boot/axios'
import { useAuthStore } from 'src/stores/authStore'
import { useCommunityStore } from 'src/stores/communityStore'
import PostCard from 'components/PostCard.vue'
import ImageUpload from 'components/ImageUpload.vue'
import ReportDialog from 'components/ReportDialog.vue'

const $q = useQuasar()
const authStore = useAuthStore()
const store = useCommunityStore()

const expanded = ref(false)
const posting = ref(false)
const destinations = ref([])
const allDestinations = ref([])
const activeType = ref(null)
const openComments = ref(new Set())
const loadingComments = ref(null)
const reportDialog = ref(false)
const reportTarget = ref(null)

const draft = reactive({ body: '', type: 'story', destination_id: null, image: null })

const typeOptions = [
  { label: 'Story', value: 'story' },
  { label: 'Travel tip', value: 'tip' },
  { label: 'Review', value: 'review' },
  { label: 'Announcement', value: 'announcement' },
]

const filters = [
  { label: 'All', value: null },
  { label: 'Stories', value: 'story' },
  { label: 'Tips', value: 'tip' },
  { label: 'Reviews', value: 'review' },
]

const placeholder = computed(() =>
  `Share something with the community, ${authStore.user?.name?.split(' ')[0] || 'traveller'}...`,
)

const feedFilters = computed(() => (activeType.value ? { type: activeType.value } : {}))

const setType = (value) => {
  activeType.value = value
  store.fetchFeed(feedFilters.value)
}

const resetDraft = () => {
  Object.assign(draft, { body: '', type: 'story', destination_id: null, image: null })
  expanded.value = false
}

const submitPost = async () => {
  posting.value = true
  try {
    await store.createPost({ ...draft, body: draft.body.trim() })
    resetDraft()
    $q.notify({ color: 'positive', icon: 'check_circle', message: 'Posted', position: 'top' })
  } catch (err) {
    $q.notify({
      color: 'negative',
      icon: 'error',
      position: 'top',
      message: err.response?.data?.errors?.body?.[0] || err.response?.data?.message || 'Could not post',
    })
  } finally {
    posting.value = false
  }
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

const filterDestinations = (val, update) => {
  update(() => {
    const needle = (val || '').toLowerCase()
    destinations.value = needle
      ? allDestinations.value.filter((d) => d.name.toLowerCase().includes(needle))
      : allDestinations.value
  })
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
    destinations.value = allDestinations.value
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
.filter-row { display: flex; gap: 7px; margin-bottom: 16px; flex-wrap: wrap; }
.filter-btn {
  padding: 6px 14px; border: 1px solid #e5dced; border-radius: 999px; background: #fff;
  font: inherit; font-size: 13px; color: #6b5a75; cursor: pointer; transition: all 0.15s ease;
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
