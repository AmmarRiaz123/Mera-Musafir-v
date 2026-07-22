<template>
  <article class="post" :class="{ 'post--agency': post.author.is_agency }">
    <!-- ── Header ──────────────────────────────────────── -->
    <header class="post-head">
      <q-avatar size="34px" class="post-avatar" @click="goToAuthor">
        <img v-if="post.author.avatar" :src="post.author.avatar" />
        <span v-else>{{ post.author.name?.[0]?.toUpperCase() }}</span>
      </q-avatar>

      <div class="head-text">
        <div class="head-line">
          <span class="post-name" @click="goToAuthor">
            {{ post.author.is_agency ? post.author.agency_name || post.author.name : post.author.name }}
          </span>
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

    <!-- ── Media (full-bleed, Instagram style) ─────────── -->
    <div v-if="post.media_url" class="media" @dblclick="onDoubleTap">
      <video
        v-if="post.media_type === 'video'"
        :src="post.media_url"
        class="media-el"
        controls
        playsinline
        preload="metadata"
      />
      <img v-else :src="post.media_url" class="media-el" alt="" />

      <!-- Heart burst on double-tap -->
      <transition name="burst">
        <q-icon v-if="burst" name="favorite" class="burst-heart" />
      </transition>

      <!-- Track pill, like a reel's audio credit -->
      <div v-if="post.audio" class="audio-pill" @click="toggleAudio">
        <q-icon :name="playing ? 'pause' : 'play_arrow'" size="14px" />
        <span class="audio-marquee">{{ post.audio.title }} · {{ post.audio.artist }}</span>
        <q-icon name="graphic_eq" size="13px" :class="{ 'eq-live': playing }" />
      </div>
    </div>

    <!-- Audio with no media still needs a player -->
    <div v-else-if="post.audio" class="audio-standalone" @click="toggleAudio">
      <q-avatar size="38px" rounded class="audio-cover">
        <img v-if="post.audio.cover" :src="post.audio.cover" />
        <q-icon v-else name="music_note" />
      </q-avatar>
      <div class="audio-info">
        <span class="audio-title">{{ post.audio.title }}</span>
        <span class="audio-artist">{{ post.audio.artist }}</span>
      </div>
      <q-btn round unelevated dense color="primary" :icon="playing ? 'pause' : 'play_arrow'" size="sm" />
    </div>

    <!-- ── Actions ─────────────────────────────────────── -->
    <footer class="actions">
      <button type="button" class="icon-btn" :class="{ 'icon-btn--liked': post.is_liked }" @click="$emit('like', post)">
        <q-icon :name="post.is_liked ? 'favorite' : 'favorite_border'" size="24px" />
      </button>
      <button type="button" class="icon-btn" @click="$emit('toggle-comments', post)">
        <q-icon name="chat_bubble_outline" size="22px" />
      </button>
      <button type="button" class="icon-btn" @click="share">
        <q-icon name="send" size="21px" />
      </button>
    </footer>

    <!-- ── Body ────────────────────────────────────────── -->
    <div class="post-body">
      <div v-if="post.likes_count" class="likes-line">
        {{ post.likes_count }} {{ post.likes_count === 1 ? 'like' : 'likes' }}
      </div>

      <p class="caption">
        <span class="caption-name" @click="goToAuthor">
          {{ post.author.is_agency ? post.author.agency_name || post.author.name : post.author.name }}
        </span>
        <span class="caption-text">{{ displayBody }}</span>
        <button v-if="isLong && !expanded" type="button" class="more-btn" @click="expanded = true">more</button>
      </p>

      <!-- Companion posts are a call to action, not just a story -->
      <div v-if="post.type === 'companion' && !post.is_mine && authStore.isLoggedIn" class="companion-cta">
        <q-btn
          unelevated no-caps rounded size="sm" color="teal-7"
          icon="chat" label="I'm interested"
          @click="$emit('message-author', post)"
        />
        <span class="companion-hint">Message {{ post.author.name?.split(' ')[0] }} about this trip</span>
      </div>

      <button
        v-if="post.comments_count"
        type="button"
        class="comments-link"
        @click="$emit('toggle-comments', post)"
      >
        View {{ post.comments_count === 1 ? '1 comment' : `all ${post.comments_count} comments` }}
      </button>

      <div class="timestamp">{{ timeAgo(post.created_at) }}</div>
    </div>

    <!-- ── Comments ────────────────────────────────────── -->
    <section v-if="showComments" class="comments">
      <div v-if="loadingComments" class="comments-loading">
        <q-spinner-dots color="primary" size="22px" />
      </div>

      <template v-else>
        <div v-for="c in comments" :key="c.id" class="comment">
          <q-avatar size="26px" class="post-avatar">
            <img v-if="c.author.avatar" :src="c.author.avatar" />
            <span v-else>{{ c.author.name?.[0]?.toUpperCase() }}</span>
          </q-avatar>
          <p class="comment-text">
            <span class="caption-name">{{ c.author.name }}</span>
            <span>{{ c.body }}</span>
            <span class="comment-time">{{ timeAgo(c.created_at) }}</span>
          </p>
          <q-btn
            v-if="c.can_delete"
            flat round dense size="xs" icon="close" color="grey-5"
            @click="$emit('delete-comment', post, c.id)"
          />
        </div>

        <div v-if="!comments.length" class="comments-empty">No comments yet.</div>

        <div v-if="authStore.isLoggedIn" class="comment-compose">
          <q-input
            v-model="draft" dense borderless
            placeholder="Add a comment..." maxlength="1000" class="col"
            @keyup.enter="submitComment"
          />
          <q-btn
            flat dense no-caps color="primary" label="Post"
            :disable="!draft.trim()" :loading="posting"
            @click="submitComment"
          />
        </div>
      </template>
    </section>
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
  'like', 'toggle-comments', 'comment', 'delete', 'report', 'delete-comment', 'message-author',
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

const isLong = computed(() => (props.post.body || '').length > 160)
const displayBody = computed(() =>
  isLong.value && !expanded.value ? props.post.body.slice(0, 160).trimEnd() + '… ' : props.post.body,
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

const share = async () => {
  const url = `${window.location.origin}/#/community?post=${props.post.id}`
  try {
    await navigator.clipboard.writeText(url)
    $q.notify({ color: 'positive', icon: 'link', message: 'Link copied', position: 'top' })
  } catch {
    $q.notify({ color: 'negative', message: 'Could not copy link', position: 'top' })
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
  border-radius: 12px;
  overflow: hidden;
}
.post--agency { border-color: #ddd0e8; }

/* ── Header ─────────────────────────────────────────── */
.post-head { display: flex; align-items: center; gap: 10px; padding: 10px 12px; }
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

.head-sub { display: flex; align-items: center; gap: 5px; margin-top: 1px; font-size: 11.5px; color: #9b8aa5; }
.dot { opacity: 0.6; }
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
  position: absolute; top: 50%; left: 50%;
  transform: translate(-50%, -50%);
  font-size: 92px; color: rgba(255, 255, 255, 0.92);
  filter: drop-shadow(0 3px 10px rgba(0, 0, 0, 0.35));
  pointer-events: none;
}
.burst-enter-active { animation: burst 0.7s ease-out; }
.burst-leave-active { opacity: 0; transition: opacity 0.2s; }
@keyframes burst {
  0%   { transform: translate(-50%, -50%) scale(0.3); opacity: 0; }
  25%  { transform: translate(-50%, -50%) scale(1.15); opacity: 1; }
  50%  { transform: translate(-50%, -50%) scale(0.95); opacity: 1; }
  100% { transform: translate(-50%, -50%) scale(1); opacity: 0; }
}

/* Audio credit pill over the media */
.audio-pill {
  position: absolute; left: 10px; bottom: 10px; max-width: calc(100% - 20px);
  display: inline-flex; align-items: center; gap: 6px;
  padding: 5px 11px; border-radius: 999px;
  background: rgba(20, 10, 26, 0.62);
  backdrop-filter: blur(6px);
  color: #fff; font-size: 11.5px; line-height: 1; cursor: pointer;
}
.audio-marquee { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.eq-live { animation: pulse 1.1s ease-in-out infinite; }
@keyframes pulse { 0%,100% { opacity: 0.5 } 50% { opacity: 1 } }

.audio-standalone {
  display: flex; align-items: center; gap: 11px; margin: 0 12px 4px;
  padding: 9px 11px; border-radius: 11px; cursor: pointer;
  background: linear-gradient(120deg, #f5eef8, #ede7f6);
}
.audio-cover { background: #4a148c; color: #fff; flex-shrink: 0; }
.audio-info { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.audio-title { font-size: 12.5px; font-weight: 600; color: #2b1b33; }
.audio-artist { font-size: 11.5px; color: #7a6a82; }

/* ── Actions ────────────────────────────────────────── */
.actions { display: flex; align-items: center; gap: 4px; padding: 8px 8px 2px; }
.icon-btn {
  display: inline-flex; padding: 5px; border: 0; background: transparent;
  color: #2b1b33; cursor: pointer; transition: transform 0.12s ease, color 0.12s ease;
}
.icon-btn:hover { opacity: 0.6; }
.icon-btn:active { transform: scale(0.88); }
.icon-btn--liked { color: #e0245e; }

/* ── Body ───────────────────────────────────────────── */
.post-body { padding: 2px 14px 12px; }
.likes-line { font-size: 13px; font-weight: 600; color: #2b1b33; margin-bottom: 4px; }

.caption {
  margin: 0; font-size: 13.5px; line-height: 1.5; color: #3a2d42;
  white-space: pre-wrap; overflow-wrap: anywhere;
}
.caption-name { font-weight: 600; color: #2b1b33; margin-right: 5px; cursor: pointer; }
.more-btn { border: 0; background: none; padding: 0; color: #9b8aa5; font: inherit; cursor: pointer; }

.companion-cta {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  margin-top: 10px; padding: 9px 11px; border-radius: 10px;
  background: #e0f2f1; border: 1px solid #b2dfdb;
}
.companion-hint { font-size: 11.5px; color: #00695c; }

.comments-link {
  display: block; margin-top: 6px; padding: 0; border: 0; background: none;
  font: inherit; font-size: 13px; color: #9b8aa5; cursor: pointer;
}
.comments-link:hover { color: #7a6a82; }

.timestamp {
  margin-top: 6px; font-size: 10.5px; letter-spacing: 0.03em;
  text-transform: uppercase; color: #b0a3b8;
}

/* ── Comments ───────────────────────────────────────── */
.comments { border-top: 1px solid #f4eff7; padding: 10px 14px 12px; }
.comments-loading { display: flex; justify-content: center; padding: 8px 0; }
.comments-empty { font-size: 12.5px; color: #b0a3b8; padding: 2px 0 8px; }

.comment { display: flex; align-items: flex-start; gap: 9px; padding: 4px 0; }
.comment-text {
  flex: 1; margin: 0; font-size: 13px; line-height: 1.45; color: #3a2d42; overflow-wrap: anywhere;
}
.comment-time { margin-left: 7px; font-size: 11px; color: #b0a3b8; }

.comment-compose {
  display: flex; align-items: center; gap: 6px;
  margin-top: 6px; padding-top: 8px; border-top: 1px solid #f7f3fa;
}
</style>
