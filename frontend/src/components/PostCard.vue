<template>
  <article class="post" :class="{ 'post--agency': post.author.is_agency }">
    <!-- Author -->
    <header class="post-head">
      <q-avatar size="40px" class="post-avatar" @click="goToAuthor">
        <img v-if="post.author.avatar" :src="post.author.avatar" />
        <span v-else>{{ post.author.name?.[0]?.toUpperCase() }}</span>
      </q-avatar>

      <div class="post-meta">
        <div class="post-author">
          <span class="post-name" @click="goToAuthor">
            {{ post.author.is_agency ? post.author.agency_name || post.author.name : post.author.name }}
          </span>
          <q-icon v-if="post.author.is_verified" name="verified" size="14px" color="deep-purple" />
          <span v-if="post.author.is_agency" class="agency-tag">Agency</span>
        </div>
        <div class="post-sub">
          <span>{{ timeAgo(post.created_at) }}</span>
          <template v-if="post.destination">
            <span class="sep">·</span>
            <router-link :to="`/destinations/${post.destination.slug}`" class="dest-tag">
              <q-icon name="place" size="12px" />{{ post.destination.name }}
            </router-link>
          </template>
        </div>
      </div>

      <span class="type-chip" :class="`type-chip--${post.type}`">{{ typeLabel }}</span>

      <q-btn v-if="authStore.isLoggedIn" flat round dense size="sm" icon="more_vert" color="grey-6">
        <q-menu auto-close anchor="bottom right" self="top right">
          <q-list style="min-width: 170px">
            <q-item v-if="post.is_mine" clickable @click="$emit('delete', post)">
              <q-item-section avatar><q-icon name="delete" size="xs" color="negative" /></q-item-section>
              <q-item-section class="text-negative">Delete post</q-item-section>
            </q-item>
            <q-item v-else clickable @click="$emit('report', post)">
              <q-item-section avatar><q-icon name="flag" size="xs" color="negative" /></q-item-section>
              <q-item-section class="text-negative">Report post</q-item-section>
            </q-item>
          </q-list>
        </q-menu>
      </q-btn>
    </header>

    <!-- Body -->
    <p class="post-body">{{ post.body }}</p>

    <img v-if="post.image" :src="post.image" class="post-image" alt="" />

    <!-- Actions -->
    <footer class="post-actions">
      <button type="button" class="action" :class="{ 'action--on': post.is_liked }" @click="$emit('like', post)">
        <q-icon :name="post.is_liked ? 'favorite' : 'favorite_border'" size="18px" />
        <span>{{ post.likes_count || '' }}</span>
      </button>

      <button type="button" class="action" @click="$emit('toggle-comments', post)">
        <q-icon name="mode_comment" size="17px" />
        <span>{{ post.comments_count || '' }}</span>
      </button>
    </footer>

    <!-- Comments -->
    <section v-if="showComments" class="comments">
      <div v-if="loadingComments" class="comments-loading">
        <q-spinner-dots color="primary" size="22px" />
      </div>

      <div v-else>
        <div v-for="c in comments" :key="c.id" class="comment">
          <q-avatar size="28px" class="post-avatar">
            <img v-if="c.author.avatar" :src="c.author.avatar" />
            <span v-else>{{ c.author.name?.[0]?.toUpperCase() }}</span>
          </q-avatar>
          <div class="comment-bubble">
            <div class="comment-name">
              {{ c.author.name }}
              <span class="comment-time">{{ timeAgo(c.created_at) }}</span>
            </div>
            <div class="comment-body">{{ c.body }}</div>
          </div>
          <q-btn
            v-if="c.can_delete"
            flat round dense size="xs" icon="close" color="grey-5"
            @click="$emit('delete-comment', post, c.id)"
          />
        </div>

        <div v-if="!comments.length" class="comments-empty">No comments yet — start the conversation.</div>

        <div v-if="authStore.isLoggedIn" class="comment-compose">
          <q-input
            v-model="draft"
            dense outlined rounded
            placeholder="Write a comment..."
            maxlength="1000"
            class="col"
            @keyup.enter="submitComment"
          />
          <q-btn
            round unelevated dense color="primary" icon="send" size="sm"
            :disable="!draft.trim()" :loading="posting"
            @click="submitComment"
          />
        </div>
      </div>
    </section>
  </article>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from 'src/stores/authStore'

const props = defineProps({
  post: { type: Object, required: true },
  comments: { type: Array, default: () => [] },
  showComments: { type: Boolean, default: false },
  loadingComments: { type: Boolean, default: false },
})
const emit = defineEmits(['like', 'toggle-comments', 'comment', 'delete', 'report', 'delete-comment'])

const router = useRouter()
const authStore = useAuthStore()

const draft = ref('')
const posting = ref(false)

const LABELS = { story: 'Story', tip: 'Tip', review: 'Review', announcement: 'Announcement' }
const typeLabel = computed(() => LABELS[props.post.type] ?? 'Story')

const goToAuthor = () => {
  if (props.post.author.is_agency && props.post.author.agency_slug) {
    router.push(`/agencies/${props.post.author.agency_slug}`)
  } else if (props.post.author.id) {
    router.push(`/profile/${props.post.author.id}`)
  }
}

const submitComment = async () => {
  const body = draft.value.trim()
  if (!body || posting.value) return
  posting.value = true
  try {
    await emit('comment', props.post, body)
    draft.value = ''
  } finally {
    posting.value = false
  }
}

const timeAgo = (iso) => {
  if (!iso) return ''
  const then = new Date(iso.replace(' ', 'T'))
  const mins = Math.floor((Date.now() - then.getTime()) / 60000)
  if (mins < 1) return 'just now'
  if (mins < 60) return `${mins}m ago`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24) return `${hrs}h ago`
  const days = Math.floor(hrs / 24)
  if (days < 7) return `${days}d ago`
  return then.toLocaleDateString('en-PK', { day: 'numeric', month: 'short' })
}
</script>

<style scoped>
.post {
  background: #fff;
  border: 1px solid #ece6f0;
  border-radius: 14px;
  padding: 16px 18px;
  transition: box-shadow 0.16s ease;
}
.post:hover { box-shadow: 0 4px 18px rgba(43, 27, 51, 0.06); }

/* Agency posts read as business announcements, not personal stories. */
.post--agency {
  border-color: #ddd0e8;
  background: linear-gradient(180deg, #faf5fd 0%, #fff 42%);
}

.post-head { display: flex; align-items: flex-start; gap: 11px; }
.post-avatar {
  background: linear-gradient(135deg, #7b1fa2, #4a148c);
  color: #fff; font-weight: 700; font-size: 15px; cursor: pointer; flex-shrink: 0;
}
.post-meta { flex: 1; min-width: 0; }
.post-author { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
.post-name { font-size: 14px; font-weight: 600; color: #2b1b33; cursor: pointer; }
.post-name:hover { text-decoration: underline; }

.agency-tag {
  padding: 1px 7px; border-radius: 999px;
  background: #4a148c; color: #fff;
  font-size: 9.5px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase;
}

.post-sub { display: flex; align-items: center; gap: 4px; font-size: 12px; color: #9b8aa5; margin-top: 2px; }
.sep { opacity: 0.6; }
.dest-tag {
  display: inline-flex; align-items: center; gap: 2px;
  color: var(--q-primary); text-decoration: none; font-weight: 500;
}
.dest-tag:hover { text-decoration: underline; }

.type-chip {
  padding: 2px 9px; border-radius: 999px; flex-shrink: 0;
  font-size: 10px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
  background: #f1ecf5; color: #7a6a82; align-self: center;
}
.type-chip--tip { background: #e3f2fd; color: #1565c0; }
.type-chip--review { background: #fff3e0; color: #ef6c00; }
.type-chip--announcement { background: #ede0f4; color: #4a148c; }

.post-body {
  margin: 12px 0 0;
  font-size: 14px;
  line-height: 1.55;
  color: #3a2d42;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.post-image {
  width: 100%;
  max-height: 420px;
  object-fit: cover;
  border-radius: 11px;
  margin-top: 12px;
  display: block;
}

.post-actions { display: flex; gap: 6px; margin-top: 12px; }
.action {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 6px 11px; border: 0; border-radius: 999px; background: transparent;
  font: inherit; font-size: 13px; color: #7a6a82; cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}
.action:hover { background: #f5eef8; color: #2b1b33; }
.action--on { color: #d81b60; }
.action--on:hover { color: #d81b60; }

/* ── Comments ─────────────────────────────────────── */
.comments { margin-top: 12px; padding-top: 12px; border-top: 1px solid #f4eff7; }
.comments-loading { display: flex; justify-content: center; padding: 10px 0; }
.comments-empty { font-size: 12.5px; color: #b0a3b8; padding: 4px 0 10px; }

.comment { display: flex; align-items: flex-start; gap: 9px; padding: 5px 0; }
.comment-bubble { flex: 1; min-width: 0; background: #f7f3fa; border-radius: 11px; padding: 8px 12px; }
.comment-name { font-size: 12.5px; font-weight: 600; color: #2b1b33; }
.comment-time { font-size: 11px; font-weight: 400; color: #9b8aa5; margin-left: 6px; }
.comment-body { font-size: 13px; color: #3a2d42; margin-top: 2px; overflow-wrap: anywhere; }

.comment-compose { display: flex; align-items: center; gap: 8px; margin-top: 10px; }
</style>
