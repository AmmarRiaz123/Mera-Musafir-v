<template>
  <article class="post" :class="{ 'post--agency': post.author.is_agency }">
    <!-- ── Author ──────────────────────────────────────── -->
    <header class="post-head">
      <q-avatar size="36px" class="post-avatar" @click="goToAuthor">
        <img v-if="post.author.avatar" :src="post.author.avatar" />
        <span v-else>{{ post.author.name?.[0]?.toUpperCase() }}</span>
      </q-avatar>

      <div class="head-text">
        <div class="head-line">
          <span class="post-name" @click="goToAuthor">{{ displayName }}</span>
          <q-icon v-if="post.author.is_verified" name="verified" size="13px" color="deep-purple" />
          <span v-if="post.author.is_agency" class="agency-tag">Agency</span>
        </div>
        <div class="head-sub">
          <span class="type-inline" :class="`type-inline--${meta.color}`">
            <q-icon :name="meta.icon" size="11px" />{{ meta.label }}
          </span>
          <template v-if="post.destination">
            <span class="dot">·</span>
            <router-link :to="`/destinations/${post.destination.slug}`" class="dest-tag">
              {{ post.destination.name }}
            </router-link>
          </template>
          <span class="dot">·</span>
          <span>{{ timeAgo(post.created_at) }}</span>
        </div>
      </div>

      <q-btn v-if="authStore.isLoggedIn" flat round dense size="sm" icon="more_horiz" color="grey-7">
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

    <!-- ── Media ───────────────────────────────────────── -->
    <div v-if="post.media_url" class="media" @dblclick="onDoubleTap">
      <video
        v-if="post.media_type === 'video'"
        :src="post.media_url" class="media-el" controls playsinline preload="metadata"
      />
      <img v-else :src="post.media_url" class="media-el" alt="" />

      <transition name="burst">
        <q-icon v-if="burst" name="favorite" class="burst-heart" />
      </transition>

      <!-- Track credit, like a reel's audio chip -->
      <button v-if="post.audio" type="button" class="audio-pill" @click="toggleAudio">
        <q-icon :name="playing ? 'pause' : 'play_arrow'" size="14px" />
        <span class="audio-pill-text">{{ post.audio.title }} · {{ post.audio.artist }}</span>
        <span class="eq" :class="{ 'eq--live': playing }"><i /><i /><i /></span>
      </button>
    </div>

    <!-- ── Caption (straight after the media) ──────────── -->
    <div class="caption-block">
      <p class="caption">
        <span class="caption-name" @click="goToAuthor">{{ displayName }}</span>
        <span>{{ displayBody }}</span>
        <button v-if="isLong && !expanded" type="button" class="more-btn" @click="expanded = true">more</button>
      </p>

      <!-- Music without media gets its own compact player -->
      <button v-if="post.audio && !post.media_url" type="button" class="audio-card" @click="toggleAudio">
        <q-avatar size="34px" rounded class="audio-cover">
          <img v-if="post.audio.cover" :src="post.audio.cover" />
          <q-icon v-else name="music_note" size="18px" />
        </q-avatar>
        <span class="audio-meta">
          <span class="audio-title">{{ post.audio.title }}</span>
          <span class="audio-artist">{{ post.audio.artist }}</span>
        </span>
        <span class="eq" :class="{ 'eq--live': playing }"><i /><i /><i /></span>
        <q-icon :name="playing ? 'pause_circle' : 'play_circle'" size="26px" class="audio-play" />
      </button>

      <!-- Companion posts are a call to action -->
      <div v-if="post.type === 'companion' && !post.is_mine && authStore.isLoggedIn" class="companion-cta">
        <q-btn
          unelevated no-caps rounded size="sm" color="teal-7"
          icon="chat" label="I'm interested" @click="$emit('message-author', post)"
        />
        <span class="companion-hint">Message {{ post.author.name?.split(' ')[0] }} about this trip</span>
      </div>
    </div>

    <!-- ── Actions (own bubble) ────────────────────────── -->
    <div class="action-bar">
      <button
        type="button" class="act" :class="{ 'act--liked': post.is_liked }"
        @click="$emit('like', post)"
      >
        <q-icon :name="post.is_liked ? 'favorite' : 'favorite_border'" size="19px" />
        <span v-if="post.likes_count">{{ post.likes_count }}</span>
        <span v-else class="act-label">Like</span>
      </button>

      <span class="act-divider" />

      <button type="button" class="act" @click="$emit('toggle-comments', post)">
        <q-icon name="chat_bubble_outline" size="17px" />
        <span v-if="post.comments_count">{{ post.comments_count }}</span>
        <span v-else class="act-label">Comment</span>
      </button>

      <span class="act-divider" />

      <button type="button" class="act" @click="$emit('share', post)">
        <q-icon name="send" size="17px" />
        <span class="act-label">Send</span>
      </button>
    </div>

    <!-- ── Comments ────────────────────────────────────── -->
    <transition name="slide">
      <section v-if="showComments" class="comments">
        <div v-if="loadingComments" class="comments-loading">
          <q-spinner-dots color="primary" size="22px" />
        </div>

        <template v-else>
          <div v-for="c in comments" :key="c.id" class="comment">
            <q-avatar size="28px" class="comment-avatar">
              <img v-if="c.author.avatar" :src="c.author.avatar" />
              <span v-else>{{ c.author.name?.[0]?.toUpperCase() }}</span>
            </q-avatar>

            <div class="bubble">
              <div class="bubble-top">
                <span class="bubble-name">{{ c.author.name }}</span>
                <q-icon v-if="c.author.is_verified" name="verified" size="11px" color="deep-purple" />
                <span class="bubble-time">{{ timeAgo(c.created_at) }}</span>
              </div>
              <div class="bubble-body">{{ c.body }}</div>
            </div>

            <q-btn
              v-if="c.can_delete"
              flat round dense size="xs" icon="close" color="grey-5" class="bubble-remove"
              @click="$emit('delete-comment', post, c.id)"
            />
          </div>

          <div v-if="!comments.length" class="comments-empty">
            <q-icon name="chat_bubble_outline" size="20px" />
            <span>No comments yet — say something nice.</span>
          </div>

          <div v-if="authStore.isLoggedIn" class="compose">
            <q-avatar size="28px" class="comment-avatar">
              <img v-if="authStore.user?.avatar" :src="authStore.user.avatar" />
              <span v-else>{{ authStore.user?.name?.[0]?.toUpperCase() }}</span>
            </q-avatar>
            <input
              v-model="draft"
              class="compose-input"
              placeholder="Add a comment…"
              maxlength="1000"
              @keyup.enter="submitComment"
            />
            <q-btn
              flat dense no-caps color="primary" label="Post"
              :disable="!draft.trim()" :loading="posting" @click="submitComment"
            />
          </div>
        </template>
      </section>
    </transition>
  </article>
</template>

<script setup>
import { ref, computed, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAuthStore } from 'src/stores/authStore'
import { postType } from 'src/utils/postTypes'

const props = defineProps({
  post: { type: Object, required: true },
  comments: { type: Array, default: () => [] },
  showComments: { type: Boolean, default: false },
  loadingComments: { type: Boolean, default: false },
})
const emit = defineEmits([
  'like', 'toggle-comments', 'comment', 'delete', 'report',
  'delete-comment', 'message-author', 'share',
])

const router = useRouter()
const $q = useQuasar()
const authStore = useAuthStore()

const draft = ref('')
const posting = ref(false)
const expanded = ref(false)
const burst = ref(false)
const playing = ref(false)

let audioEl = null

const meta = computed(() => postType(props.post.type))
const displayName = computed(() =>
  props.post.author.is_agency
    ? props.post.author.agency_name || props.post.author.name
    : props.post.author.name,
)

const isLong = computed(() => (props.post.body || '').length > 180)
const displayBody = computed(() =>
  isLong.value && !expanded.value ? props.post.body.slice(0, 180).trimEnd() + '… ' : props.post.body,
)

const goToAuthor = () => {
  if (props.post.author.is_agency && props.post.author.agency_slug) {
    router.push(`/agencies/${props.post.author.agency_slug}`)
  } else if (props.post.author.id) {
    router.push(`/profile/${props.post.author.id}`)
  }
}

// Instagram's double-tap: always likes, never unlikes.
const onDoubleTap = () => {
  if (!authStore.isLoggedIn) return
  if (!props.post.is_liked) emit('like', props.post)
  burst.value = true
  setTimeout(() => (burst.value = false), 700)
}

const toggleAudio = () => {
  const url = props.post.audio?.audio_url
  if (!url) return

  if (!audioEl) {
    audioEl = new Audio(url)
    audioEl.addEventListener('ended', () => (playing.value = false))
  }

  if (playing.value) {
    audioEl.pause()
    playing.value = false
  } else {
    audioEl.play().then(() => (playing.value = true)).catch(() => {
      $q.notify({ color: 'negative', message: 'Could not play this track', position: 'top' })
    })
  }
}

onUnmounted(() => {
  if (audioEl) {
    audioEl.pause()
    audioEl = null
  }
})

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
  if (mins < 60) return `${mins}m`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24) return `${hrs}h`
  const days = Math.floor(hrs / 24)
  if (days < 7) return `${days}d`
  return then.toLocaleDateString('en-PK', { day: 'numeric', month: 'short' })
}
</script>

<style scoped>
.post {
  background: #fff;
  border: 1px solid #ece6f0;
  border-radius: 14px;
  overflow: hidden;
}
.post--agency { border-color: #ddd0e8; }

/* ── Author ─────────────────────────────────────────── */
.post-head { display: flex; align-items: center; gap: 10px; padding: 12px 14px; }
.post-avatar {
  background: linear-gradient(135deg, #7b1fa2, #4a148c);
  color: #fff; font-weight: 700; font-size: 13px; cursor: pointer; flex-shrink: 0;
}
.head-text { flex: 1; min-width: 0; }
.head-line { display: flex; align-items: center; gap: 5px; }
.post-name { font-size: 13.5px; font-weight: 600; color: #2b1b33; cursor: pointer; }
.post-name:hover { opacity: 0.7; }

.agency-tag {
  padding: 1px 6px; border-radius: 999px; background: #4a148c; color: #fff;
  font-size: 9px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase;
}

.head-sub { display: flex; align-items: center; gap: 5px; margin-top: 2px; font-size: 11.5px; color: #9b8aa5; }
.dot { opacity: 0.55; }
.dest-tag { color: var(--q-primary); text-decoration: none; font-weight: 500; }
.dest-tag:hover { text-decoration: underline; }

.type-inline { display: inline-flex; align-items: center; gap: 3px; font-weight: 600; }
.type-inline--teal { color: #00796b; }
.type-inline--purple { color: #7b1fa2; }
.type-inline--blue { color: #1565c0; }
.type-inline--indigo { color: #3949ab; }
.type-inline--amber { color: #ef6c00; }
.type-inline--red { color: #c62828; }
.type-inline--green { color: #2e7d32; }
.type-inline--cyan { color: #00838f; }
.type-inline--pink { color: #ad1457; }
.type-inline--deep { color: #4a148c; }

/* ── Media ──────────────────────────────────────────── */
.media { position: relative; background: #1a1020; line-height: 0; user-select: none; }
.media-el { width: 100%; max-height: 560px; object-fit: cover; display: block; }
video.media-el { object-fit: contain; background: #000; }

.burst-heart {
  position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
  font-size: 92px; color: rgba(255, 255, 255, 0.92);
  filter: drop-shadow(0 3px 10px rgba(0, 0, 0, 0.35)); pointer-events: none;
}
.burst-enter-active { animation: burst 0.7s ease-out; }
.burst-leave-active { opacity: 0; transition: opacity 0.2s; }
@keyframes burst {
  0%   { transform: translate(-50%, -50%) scale(0.3); opacity: 0; }
  25%  { transform: translate(-50%, -50%) scale(1.15); opacity: 1; }
  50%  { transform: translate(-50%, -50%) scale(0.95); opacity: 1; }
  100% { transform: translate(-50%, -50%) scale(1); opacity: 0; }
}

.audio-pill {
  position: absolute; left: 10px; bottom: 10px; max-width: calc(100% - 20px);
  display: inline-flex; align-items: center; gap: 7px;
  padding: 6px 12px; border: 0; border-radius: 999px;
  background: rgba(20, 10, 26, 0.66); backdrop-filter: blur(8px);
  color: #fff; font: inherit; font-size: 11.5px; line-height: 1; cursor: pointer;
}
.audio-pill-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Little equaliser that animates while a track plays */
.eq { display: inline-flex; align-items: flex-end; gap: 2px; height: 11px; }
.eq i { width: 2px; height: 4px; border-radius: 1px; background: currentColor; opacity: 0.55; }
.eq--live i { animation: bounce 0.9s ease-in-out infinite; opacity: 1; }
.eq--live i:nth-child(2) { animation-delay: 0.15s; }
.eq--live i:nth-child(3) { animation-delay: 0.3s; }
@keyframes bounce { 0%,100% { height: 3px } 50% { height: 11px } }

/* ── Caption ────────────────────────────────────────── */
.caption-block { padding: 12px 14px 4px; }
.caption {
  margin: 0; font-size: 13.5px; line-height: 1.5; color: #3a2d42;
  white-space: pre-wrap; overflow-wrap: anywhere;
}
.caption-name { font-weight: 600; color: #2b1b33; margin-right: 5px; cursor: pointer; }
.more-btn { border: 0; background: none; padding: 0; color: #9b8aa5; font: inherit; cursor: pointer; }

.audio-card {
  display: flex; align-items: center; gap: 10px; width: 100%; margin-top: 10px;
  padding: 8px 10px; border: 1px solid #e6dcee; border-radius: 12px;
  background: linear-gradient(120deg, #faf5fd, #f2ebf8);
  font: inherit; text-align: left; cursor: pointer; color: #4a148c;
}
.audio-card:hover { border-color: #d3bfe0; }
.audio-cover { background: #4a148c; color: #fff; flex-shrink: 0; }
.audio-meta { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.audio-title {
  font-size: 12.5px; font-weight: 600; color: #2b1b33;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.audio-artist { font-size: 11.5px; color: #7a6a82; }
.audio-play { color: var(--q-primary); flex-shrink: 0; }

.companion-cta {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  margin-top: 10px; padding: 9px 11px; border-radius: 11px;
  background: #e0f2f1; border: 1px solid #b2dfdb;
}
.companion-hint { font-size: 11.5px; color: #00695c; }

/* ── Actions: its own bubble ────────────────────────── */
.action-bar {
  display: flex; align-items: center; justify-content: space-around;
  margin: 10px 14px 12px; padding: 3px;
  border-radius: 999px; background: #f7f3fa; border: 1px solid #f0eaf4;
}
.act {
  flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 6px;
  padding: 7px 10px; border: 0; border-radius: 999px; background: transparent;
  font: inherit; font-size: 12.5px; font-weight: 500; color: #6b5a75; cursor: pointer;
  transition: background 0.14s ease, color 0.14s ease, transform 0.1s ease;
}
.act:hover { background: #fff; color: #2b1b33; }
.act:active { transform: scale(0.96); }
.act--liked { color: #e0245e; }
.act--liked:hover { color: #e0245e; }
.act-label { font-size: 12.5px; }
.act-divider { width: 1px; height: 16px; background: #e8e0ee; flex-shrink: 0; }

/* ── Comments ───────────────────────────────────────── */
.slide-enter-active, .slide-leave-active { transition: opacity 0.18s ease; }
.slide-enter-from, .slide-leave-to { opacity: 0; }

.comments { border-top: 1px solid #f4eff7; padding: 12px 14px 14px; background: #fdfcfe; }
.comments-loading { display: flex; justify-content: center; padding: 8px 0; }
.comments-empty {
  display: flex; align-items: center; gap: 8px; justify-content: center;
  padding: 14px 0; font-size: 12.5px; color: #b0a3b8;
}

.comment { display: flex; align-items: flex-start; gap: 9px; padding: 4px 0; }
.comment-avatar {
  background: linear-gradient(135deg, #9c5bb8, #6a1b9a);
  color: #fff; font-weight: 700; font-size: 11px; flex-shrink: 0;
}
.bubble {
  flex: 1; min-width: 0; padding: 8px 12px;
  border-radius: 4px 14px 14px 14px; background: #fff; border: 1px solid #efe9f3;
}
.bubble-top { display: flex; align-items: center; gap: 5px; }
.bubble-name { font-size: 12.5px; font-weight: 600; color: #2b1b33; }
.bubble-time { font-size: 10.5px; color: #b0a3b8; margin-left: auto; }
.bubble-body { font-size: 13px; line-height: 1.45; color: #3a2d42; margin-top: 2px; overflow-wrap: anywhere; }
.bubble-remove { align-self: center; }

.compose {
  display: flex; align-items: center; gap: 9px;
  margin-top: 10px; padding: 4px 4px 4px 4px;
  border-radius: 999px; background: #fff; border: 1px solid #e8e0ee;
}
.compose-input {
  flex: 1; min-width: 0; border: 0; outline: none; background: transparent;
  font: inherit; font-size: 13px; color: #3a2d42; padding: 5px 0;
}
.compose-input::placeholder { color: #b0a3b8; }
</style>
