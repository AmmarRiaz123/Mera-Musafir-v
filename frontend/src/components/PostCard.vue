<template>
  <article ref="root" class="post" :class="{ 'post--agency': post.author.is_agency, 'post--focus': focus }">
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
          <span v-if="focus">{{ timeAgo(post.created_at) }}</span>
          <button v-else type="button" class="time-btn" @click="$emit('open', post)">
            {{ timeAgo(post.created_at) }}
          </button>
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
    <div
      v-if="gallery.length"
      class="media"
      :class="{ 'media--carousel': gallery.length > 1 }"
      @dblclick="onDoubleTap"
    >
      <div class="slides" :style="{ transform: `translateX(-${slide * 100}%)` }">
        <div v-for="(m, i) in gallery" :key="i" class="slide">
          <video
            v-if="m.type === 'video'"
            :src="m.url" class="media-el" controls playsinline preload="metadata"
          />
          <img v-else :src="m.url" class="media-el" alt="" />
        </div>
      </div>

      <!-- Carousel controls, only when there's more than one -->
      <template v-if="gallery.length > 1">
        <button v-if="slide > 0" type="button" class="nav nav--prev" @click.stop="slide--">
          <q-icon name="chevron_left" size="20px" />
        </button>
        <button v-if="slide < gallery.length - 1" type="button" class="nav nav--next" @click.stop="slide++">
          <q-icon name="chevron_right" size="20px" />
        </button>
        <span class="count-pill">{{ slide + 1 }}/{{ gallery.length }}</span>
        <div class="dots">
          <span v-for="(m, i) in gallery" :key="i" class="dot-i" :class="{ 'dot-i--on': i === slide }" @click.stop="slide = i" />
        </div>
      </template>

      <transition name="burst">
        <q-icon v-if="burst" name="favorite" class="burst-heart" />
      </transition>

      <!-- Track credit. Tapping toggles sound for the whole feed, not just
           this post — matching how Reels behaves. -->
      <button v-if="post.audio" type="button" class="audio-pill" @click.stop="audioStore.toggleMute()">
        <q-icon :name="soundIcon" size="14px" />
        <span class="audio-pill-text">{{ audioLabel }}</span>
        <span class="eq" :class="{ 'eq--live': isPlaying }"><i /><i /><i /></span>
      </button>
    </div>

    <!-- ── Caption (straight after the media) ──────────── -->
    <div class="caption-block">
      <p class="caption">
        <span class="caption-name" @click="goToAuthor">{{ displayName }}</span>
        <span>{{ displayBody }}</span>
        <button v-if="isLong" type="button" class="more-btn" @click="$emit('open', post)">more</button>
      </p>

      <!-- Music without media gets its own compact player -->
      <button v-if="post.audio && !gallery.length" type="button" class="audio-card" @click="audioStore.toggleMute()">
        <q-avatar size="34px" rounded class="audio-cover">
          <img v-if="post.audio.cover" :src="post.audio.cover" />
          <q-icon v-else name="music_note" size="18px" />
        </q-avatar>
        <span class="audio-meta">
          <span class="audio-title">{{ post.audio.title }}</span>
          <span class="audio-artist">{{ post.audio.artist }}</span>
        </span>
        <span class="eq" :class="{ 'eq--live': isPlaying }"><i /><i /><i /></span>
        <q-icon :name="soundIcon" size="24px" class="audio-play" />
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

    <!-- Only the newest few comments live in the feed; the rest are one click
         away in the single-post view. -->
    <button
      v-if="!focus && post.comments_count > 2"
      type="button" class="view-all" @click="$emit('open', post)"
    >
      View all {{ post.comments_count }} comments
    </button>

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
              <div v-if="c.body" class="bubble-body">{{ c.body }}</div>
              <img v-if="c.media_url" :src="c.media_url" class="bubble-media" alt="" />
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

          <div v-if="authStore.isLoggedIn" class="compose-wrap">
            <!-- Attachment preview -->
            <div v-if="attachment" class="attach-preview">
              <img :src="attachment.previewUrl || attachment.url" alt="" />
              <q-btn round dense unelevated size="xs" icon="close" class="attach-x" @click="attachment = null" />
            </div>

            <div class="compose">
              <q-avatar size="28px" class="comment-avatar">
                <img v-if="authStore.user?.avatar" :src="authStore.user.avatar" />
                <span v-else>{{ authStore.user?.name?.[0]?.toUpperCase() }}</span>
              </q-avatar>
              <input
                v-model="draft"
                class="compose-input"
                :placeholder="attachment ? 'Add a caption (optional)…' : 'Add a comment…'"
                maxlength="1000"
                @keyup.enter="submitComment"
              />
              <button type="button" class="compose-icon" :disabled="uploading" @click="pickCommentPhoto">
                <q-icon name="image" size="18px" />
                <q-tooltip>Photo</q-tooltip>
              </button>
              <button type="button" class="compose-icon" @click="gifDialog = true">
                <q-icon name="gif_box" size="18px" />
                <q-tooltip>GIF</q-tooltip>
              </button>
              <q-spinner v-if="uploading" color="primary" size="18px" />
              <q-btn
                v-else
                flat dense no-caps color="primary" label="Post"
                :disable="!canComment" :loading="posting" @click="submitComment"
              />
            </div>

            <input ref="commentFile" type="file" class="hidden" accept="image/*" @change="onCommentFile" />
          </div>
        </template>
      </section>
    </transition>
    <GifPicker v-model="gifDialog" @picked="onGifPicked" />
  </article>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAuthStore } from 'src/stores/authStore'
import { postType } from 'src/utils/postTypes'
import { api } from 'src/boot/axios'
import GifPicker from 'components/GifPicker.vue'
import { useFeedAudioStore } from 'src/stores/feedAudioStore'

const props = defineProps({
  post: { type: Object, required: true },
  comments: { type: Array, default: () => [] },
  showComments: { type: Boolean, default: false },
  loadingComments: { type: Boolean, default: false },
  // The single-post view. Captions run to 2000 characters, so in the feed they
  // get clipped — here there's nothing below to protect, so show the lot.
  focus: { type: Boolean, default: false },
})
const emit = defineEmits([
  'like', 'toggle-comments', 'comment', 'delete', 'report',
  'delete-comment', 'message-author', 'share', 'open',
])

const router = useRouter()
const $q = useQuasar()
const authStore = useAuthStore()
const audioStore = useFeedAudioStore()

const draft = ref('')
const posting = ref(false)
const slide = ref(0)
const attachment = ref(null)
const uploading = ref(false)
const gifDialog = ref(false)
const commentFile = ref(null)

// Older posts only have media_url; the resource normalises both into `gallery`.
const gallery = computed(() => props.post.gallery || [])

const canComment = computed(() => !!draft.value.trim() || !!attachment.value)

const pickCommentPhoto = () => commentFile.value?.click()

const onCommentFile = async (e) => {
  const file = e.target.files?.[0]
  e.target.value = ''
  if (!file) return

  uploading.value = true
  try {
    const fd = new FormData()
    fd.append('file', file)
    fd.append('type', 'post_media')
    // Clearing Content-Type lets the browser set the multipart boundary.
    const { data } = await api.post('/api/v1/uploads', fd, { headers: { 'Content-Type': undefined } })
    attachment.value = { url: data.path, type: data.media_type === 'gif' ? 'gif' : 'image', previewUrl: data.url }
  } catch (err) {
    $q.notify({
      color: 'negative', position: 'top',
      message: err.response?.data?.errors?.file?.[0] || 'Could not attach that image',
    })
  } finally {
    uploading.value = false
  }
}

const onGifPicked = (gif) => {
  attachment.value = { url: gif.url, type: 'gif', previewUrl: gif.url }
}
const burst = ref(false)
const root = ref(null)

// This post owns the feed's audio only while it's the one in view.
const isActive = computed(() => audioStore.activeId === props.post.id)
const isPlaying = computed(() => isActive.value && audioStore.playing && !audioStore.muted)

const soundIcon = computed(() => {
  if (audioStore.muted) return 'volume_off'
  if (audioStore.blocked) return 'touch_app'
  return 'volume_up'
})

const audioLabel = computed(() => {
  if (audioStore.blocked && !audioStore.muted) return 'Tap for sound'
  return `${props.post.audio?.title} · ${props.post.audio?.artist}`
})

// A post "takes over" the audio once most of it is on screen.
let observer = null

onMounted(() => {
  if (!props.post.audio || !root.value) return

  observer = new IntersectionObserver(
    ([entry]) => {
      if (entry.isIntersecting) {
        audioStore.activate(props.post.id, props.post.audio)
      } else {
        audioStore.deactivate(props.post.id)
      }
    },
    // 55% keeps handover clean: two posts can't both claim it.
    { threshold: 0.55 },
  )
  observer.observe(root.value)
})

const meta = computed(() => postType(props.post.type))
const displayName = computed(() =>
  props.post.author.is_agency
    ? props.post.author.agency_name || props.post.author.name
    : props.post.author.name,
)

const isLong = computed(() => !props.focus && (props.post.body || '').length > 180)
const displayBody = computed(() =>
  isLong.value ? props.post.body.slice(0, 180).trimEnd() + '… ' : props.post.body,
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

onUnmounted(() => {
  observer?.disconnect()
  // If this card owned the audio, hand it back rather than leaving it playing.
  audioStore.deactivate(props.post.id)
})

const submitComment = async () => {
  if (!canComment.value || posting.value) return
  posting.value = true
  try {
    await emit('comment', props.post, {
      body: draft.value.trim() || null,
      media_url: attachment.value?.url || null,
      media_type: attachment.value?.type || null,
    })
    draft.value = ''
    attachment.value = null
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
  /* Media height is the main lever on whether a whole post fits on screen. */
  --media-h: clamp(220px, 42vh, 430px);
  background: #fff;
  border: 1px solid #ece6f0;
  border-radius: 14px;
  overflow: hidden;
}
.post--agency { border-color: #ddd0e8; }

/* ── Author ─────────────────────────────────────────── */
.post-head { display: flex; align-items: center; gap: 10px; padding: 10px 14px; }
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
.media { position: relative; background: #1a1020; line-height: 0; user-select: none; overflow: hidden; }
/* Every post uses the same media frame: full card width and a fixed height, so
   a whole post fits the viewport and mixed aspect ratios don't leave gaps.
   (aspect-ratio + max-height was shrinking the *width* instead of the height,
   which is what left the empty strip beside the image.) */
.media { height: var(--media-h); }
.slides { display: flex; height: 100%; transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1); }
.slide { min-width: 100%; height: 100%; }
.media-el { width: 100%; height: 100%; object-fit: cover; display: block; }
video.media-el { object-fit: contain; }

.nav {
  position: absolute; top: 50%; transform: translateY(-50%);
  width: 30px; height: 30px; border: 0; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  background: rgba(255, 255, 255, 0.88); color: #2b1b33; cursor: pointer;
  box-shadow: 0 1px 5px rgba(0, 0, 0, 0.18);
}
.nav--prev { left: 8px; }
.nav--next { right: 8px; }
.count-pill {
  position: absolute; top: 10px; right: 10px;
  padding: 3px 9px; border-radius: 999px;
  background: rgba(20, 10, 26, 0.66); color: #fff; font-size: 11px; line-height: 1.3;
}
.dots { position: absolute; bottom: 10px; left: 0; right: 0; display: flex; justify-content: center; gap: 5px; }
.dot-i {
  width: 6px; height: 6px; border-radius: 50%; cursor: pointer;
  background: rgba(255, 255, 255, 0.5); transition: background 0.15s ease;
}
.dot-i--on { background: #fff; }
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
.audio-pill:hover { background: rgba(20, 10, 26, 0.82); }

/* Little equaliser that animates while a track plays */
.eq { display: inline-flex; align-items: flex-end; gap: 2px; height: 11px; }
.eq i { width: 2px; height: 4px; border-radius: 1px; background: currentColor; opacity: 0.55; }
.eq--live i { animation: bounce 0.9s ease-in-out infinite; opacity: 1; }
.eq--live i:nth-child(2) { animation-delay: 0.15s; }
.eq--live i:nth-child(3) { animation-delay: 0.3s; }
@keyframes bounce { 0%,100% { height: 3px } 50% { height: 11px } }

/* ── Caption ────────────────────────────────────────── */
.caption-block { padding: 10px 14px 2px; }
.caption {
  margin: 0; font-size: 13.5px; line-height: 1.45; color: #3a2d42;
  white-space: pre-wrap; overflow-wrap: anywhere;
  display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}
/* Trimming the text isn't enough — the three-line clamp would still hide most
   of a 2000-character caption in the view whose whole job is showing it. */
.post--focus .caption { display: block; overflow: visible; }
.caption-name { font-weight: 600; color: #2b1b33; margin-right: 5px; cursor: pointer; }
.more-btn { border: 0; background: none; padding: 0; color: #9b8aa5; font: inherit; cursor: pointer; }
.more-btn:hover { color: var(--q-primary); text-decoration: underline; }
.time-btn {
  border: 0; background: none; padding: 0; font: inherit; color: inherit; cursor: pointer;
}
.time-btn:hover { color: var(--q-primary); text-decoration: underline; }
.view-all {
  display: block; width: 100%; text-align: left;
  border: 0; background: none; cursor: pointer;
  padding: 2px 14px 8px; font-size: 13px; color: #9b8aa5;
}
.view-all:hover { color: var(--q-primary); }

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
  margin: 8px 14px 10px; padding: 2px;
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
.bubble-media {
  display: block; margin-top: 6px; max-width: 220px; max-height: 200px;
  border-radius: 10px; border: 1px solid #efe9f3;
}
.bubble-remove { align-self: center; }

.compose-wrap { margin-top: 10px; }
.attach-preview { position: relative; display: inline-block; margin-bottom: 8px; }
.attach-preview img {
  max-height: 110px; max-width: 180px; border-radius: 10px; display: block;
  border: 1px solid #e8e0ee;
}
.attach-x { position: absolute; top: 4px; right: 4px; background: rgba(20, 10, 26, 0.6); color: #fff; }

.compose {
  display: flex; align-items: center; gap: 7px; padding: 4px;
  border-radius: 999px; background: #fff; border: 1px solid #e8e0ee;
}
.compose-icon {
  display: inline-flex; padding: 5px; border: 0; border-radius: 50%;
  background: transparent; color: #9b8aa5; cursor: pointer; transition: all 0.14s ease;
}
.compose-icon:hover:not(:disabled) { background: #f5eef8; color: var(--q-primary); }
.compose-icon:disabled { opacity: 0.4; cursor: not-allowed; }
.hidden { display: none; }
.compose-input {
  flex: 1; min-width: 0; border: 0; outline: none; background: transparent;
  font: inherit; font-size: 13px; color: #3a2d42; padding: 5px 0;
}
.compose-input::placeholder { color: #b0a3b8; }
</style>
