<template>
  <section class="composer" :class="{ 'composer--dialog': dialog }">
    <!-- Collapsed prompt (inline mode only) -->
    <div v-if="!dialog && !expanded" class="prompt" @click="expanded = true">
      <q-avatar size="34px" class="prompt-avatar">
        <img v-if="authStore.user?.avatar" :src="authStore.user.avatar" />
        <span v-else>{{ authStore.user?.name?.[0]?.toUpperCase() }}</span>
      </q-avatar>
      <span class="prompt-text">Share something with the community…</span>
      <q-icon name="add_photo_alternate" size="20px" color="primary" />
    </div>

    <!-- Editor -->
    <div v-else class="editor">
      <header class="editor-head">
        <q-avatar size="34px" class="prompt-avatar">
          <img v-if="authStore.user?.avatar" :src="authStore.user.avatar" />
          <span v-else>{{ authStore.user?.name?.[0]?.toUpperCase() }}</span>
        </q-avatar>
        <div class="editor-who">
          <span class="editor-name">{{ authStore.user?.name }}</span>
          <span v-if="dialog" class="editor-hint">New post</span>
        </div>
        <q-space />
        <q-btn flat round dense size="sm" icon="close" color="grey-7" @click="reset" />
      </header>

      <!-- Category -->
      <div class="type-scroll">
        <button
          v-for="t in availableTypes"
          :key="t.value"
          type="button"
          class="type-pill"
          :class="{ 'type-pill--active': form.type === t.value }"
          @click="form.type = t.value"
        >
          <q-icon :name="t.icon" size="14px" />{{ t.label }}
        </button>
      </div>

      <div class="body-wrap">
        <q-input
          v-model="form.body"
          class="body-input"
          borderless
          autogrow
          :rows="3"
          maxlength="2000"
          :placeholder="bodyPlaceholder"
        />
      </div>

      <!-- Selected media (multiple allowed) -->
      <div v-if="media.length" class="gallery-strip">
        <div v-for="(m, i) in media" :key="i" class="tile">
          <video v-if="m.type === 'video'" :src="m.previewUrl" :poster="m.poster || undefined" class="tile-el" preload="metadata" />
          <img v-else :src="m.previewUrl" class="tile-el" alt="" />
          <span class="tile-kind" v-if="m.type !== 'image'">{{ m.type.toUpperCase() }}</span>
          <q-btn round dense unelevated class="tile-x" icon="close" size="xs" @click="media.splice(i, 1)" />
        </div>
        <button v-if="media.length < 10" type="button" class="tile tile--add" @click="pickFile">
          <q-icon name="add" size="22px" />
        </button>
      </div>

      <!-- Selected track -->
      <div v-if="form.audio" class="track-chip">
        <q-icon name="music_note" size="15px" />
        <span class="track-text">{{ form.audio.title }} · {{ form.audio.artist }}</span>
        <q-btn flat round dense size="xs" icon="close" @click="form.audio = null" />
      </div>

      <!-- Destination -->
      <q-select
        v-model="form.destination_id"
        :options="destinationOptions"
        option-value="id" option-label="name" emit-value map-options
        outlined dense clearable use-input hide-selected fill-input
        input-debounce="200"
        label="Tag a destination"
        class="dest-select"
        @filter="filterDestinations"
      >
        <template v-slot:prepend><q-icon name="place" size="18px" color="primary" /></template>
      </q-select>

      <!-- Attach bar -->
      <div class="attach-bar">
        <span class="attach-label">Add</span>
        <button type="button" class="attach" :disabled="uploading || media.length >= 10" @click="pickFile">
          <q-icon name="image" size="20px" /><span>Photo / Video</span>
        </button>
        <button type="button" class="attach" @click="openGifs">
          <q-icon name="gif_box" size="20px" /><span>GIF</span>
        </button>
        <button type="button" class="attach" @click="openMusic">
          <q-icon name="library_music" size="20px" /><span>Music</span>
        </button>
        <q-space />
        <span class="counter">{{ form.body.length }}/2000</span>
      </div>

      <div v-if="uploading" class="uploading">
        <q-linear-progress indeterminate color="primary" rounded size="3px" />
        <span>Uploading…</span>
      </div>

      <div class="editor-actions">
        <q-btn flat no-caps color="grey-7" label="Cancel" @click="reset" />
        <q-btn
          unelevated rounded no-caps color="primary" label="Share"
          :disable="!canPost" :loading="posting" @click="submit"
        />
      </div>

      <input ref="fileInput" type="file" multiple class="hidden" accept="image/*,video/mp4,video/webm,video/quicktime" @change="onFile" />
    </div>

    <!-- ── GIF picker ─────────────────────────────────── -->
    <q-dialog v-model="gifDialog">
      <q-card class="picker">
        <q-card-section class="picker-head">
          <div class="text-h6">Choose a GIF</div>
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>

        <q-card-section class="q-pt-none">
          <q-input
            v-model="gifQuery" outlined dense rounded clearable
            placeholder="Search GIFs…" debounce="400"
            @update:model-value="searchGifs"
          >
            <template v-slot:prepend><q-icon name="search" /></template>
          </q-input>
        </q-card-section>

        <q-card-section class="picker-body">
          <div v-if="gifNotice" class="picker-notice">
            <q-icon name="info" size="26px" />
            <div>{{ gifNotice }}</div>
          </div>
          <div v-else-if="gifLoading" class="picker-loading"><q-spinner-dots color="primary" size="28px" /></div>
          <div v-else-if="!gifs.length" class="picker-notice"><div>No GIFs found.</div></div>
          <div v-else class="gif-grid">
            <button v-for="g in gifs" :key="g.id" type="button" class="gif-cell" @click="pickGif(g)">
              <img :src="g.preview" :alt="g.title" loading="lazy" />
            </button>
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- ── Music picker ───────────────────────────────── -->
    <q-dialog v-model="musicDialog">
      <q-card class="picker">
        <q-card-section class="picker-head">
          <div>
            <div class="text-h6">Add music</div>
            <div class="picker-sub">Royalty-free tracks, free to use with credit</div>
          </div>
          <q-btn flat round dense icon="close" v-close-popup />
        </q-card-section>

        <q-card-section class="q-pt-none">
          <q-input
            v-model="musicQuery" outlined dense rounded clearable
            placeholder="Search tracks or artists…" debounce="400"
            @update:model-value="onMusicSearch"
          >
            <template v-slot:prepend><q-icon name="search" /></template>
          </q-input>

          <div class="mood-rail">
            <button
              v-for="m in moodShelves" :key="m.slug"
              type="button" class="mood-pill"
              :class="{ 'mood-pill--active': activeMood === m.slug && !musicQuery }"
              @click="pickMood(m.slug)"
            >
              <q-icon :name="MOOD_ICONS[m.slug] || 'music_note'" size="13px" />{{ m.label }}
            </button>
          </div>
        </q-card-section>

        <q-card-section class="picker-body">
          <div v-if="musicNotice" class="picker-notice">
            <q-icon name="info" size="26px" />
            <div>{{ musicNotice }}</div>
          </div>
          <div v-else-if="musicLoading" class="picker-loading"><q-spinner-dots color="primary" size="28px" /></div>
          <div v-else-if="!tracks.length" class="picker-notice">
            <q-icon name="graphic_eq" size="26px" />
            <div>{{ musicQuery ? 'Nothing matched that search.' : 'This shelf is quiet right now — try another mood.' }}</div>
          </div>
          <q-list v-else separator>
            <q-item v-for="t in tracks" :key="t.id" clickable @click="pickTrack(t)">
              <q-item-section avatar>
                <q-avatar rounded size="42px" class="audio-cover">
                  <img v-if="t.cover" :src="t.cover" />
                  <q-icon v-else name="music_note" />
                </q-avatar>
              </q-item-section>
              <q-item-section>
                <q-item-label class="text-weight-medium">{{ t.title }}</q-item-label>
                <q-item-label caption>{{ t.artist }}</q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-btn
                  round flat dense color="primary"
                  :icon="previewId === t.id ? 'pause' : 'play_arrow'"
                  @click.stop="previewTrack(t)"
                />
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>
      </q-card>
    </q-dialog>
  </section>
</template>

<script setup>
import { ref, reactive, computed, onUnmounted } from 'vue'
import { useQuasar } from 'quasar'
import { api } from 'src/boot/axios'
import { useAuthStore } from 'src/stores/authStore'
import { POST_TYPES, travellerTypes } from 'src/utils/postTypes'

const props = defineProps({
  destinations: { type: Array, default: () => [] },
  isAgency: { type: Boolean, default: false },
  // In a dialog there's no collapsed state — open straight into the editor.
  dialog: { type: Boolean, default: false },
})
const emit = defineEmits(['created', 'close'])

const $q = useQuasar()
const authStore = useAuthStore()

const expanded = ref(props.dialog)
const posting = ref(false)
const uploading = ref(false)
const fileInput = ref(null)

const form = reactive({ body: '', type: 'story', destination_id: null, audio: null })

// Up to 10 items: photos, videos and GIFs mixed.
const media = ref([])

// Agencies get the announcement category; travellers don't.
const availableTypes = computed(() => (props.isAgency ? POST_TYPES : travellerTypes))

const bodyPlaceholder = computed(() => ({
  companion: 'Where are you going, when, and who are you looking for?',
  question:  'What do you want to ask the community?',
  alert:     'What should other travellers know right now?',
  gear:      'What worked, what did you regret packing?',
  budget:    'Break down what the trip actually cost.',
  safety:    'What happened, and where?',
}[form.type] ?? 'Share something with the community…'))

const canPost = computed(
  () => (form.body.trim().length > 0 || media.value.length > 0) && !uploading.value,
)

// ── Destinations ────────────────────────────────────
const destinationOptions = ref([])
const filterDestinations = (val, update) => {
  update(() => {
    const needle = (val || '').toLowerCase()
    destinationOptions.value = needle
      ? props.destinations.filter((d) => d.name.toLowerCase().includes(needle))
      : props.destinations
  })
}

// ── File upload ─────────────────────────────────────
const pickFile = () => fileInput.value?.click()

// Draw a video's first frame to a canvas and upload it as an image poster.
const capturePoster = (file) => new Promise((resolve, reject) => {
  const url = URL.createObjectURL(file)
  const video = document.createElement('video')
  video.muted = true
  video.playsInline = true
  video.preload = 'metadata'
  video.src = url

  const cleanup = () => URL.revokeObjectURL(url)

  video.addEventListener('loadeddata', () => {
    // A hair past zero avoids a black leading frame on some encodings.
    video.currentTime = Math.min(0.1, video.duration || 0.1)
  })
  video.addEventListener('seeked', async () => {
    try {
      const canvas = document.createElement('canvas')
      canvas.width = video.videoWidth
      canvas.height = video.videoHeight
      canvas.getContext('2d').drawImage(video, 0, 0)
      const blob = await new Promise((r) => canvas.toBlob(r, 'image/jpeg', 0.8))
      cleanup()
      if (!blob) return reject(new Error('no frame'))

      const fd = new FormData()
      fd.append('file', new File([blob], 'poster.jpg', { type: 'image/jpeg' }))
      fd.append('type', 'post_media')
      const { data } = await api.post('/api/v1/uploads', fd, { headers: { 'Content-Type': undefined } })
      resolve(data.path)
    } catch (err) {
      cleanup()
      reject(err)
    }
  })
  video.addEventListener('error', () => { cleanup(); reject(new Error('video load failed')) })
})

const onFile = async (e) => {
  const files = Array.from(e.target.files || [])
  e.target.value = ''
  if (!files.length) return

  uploading.value = true
  try {
    for (const file of files.slice(0, 10 - media.value.length)) {
      const fd = new FormData()
      fd.append('file', file)
      fd.append('type', 'post_media')
      // Clearing Content-Type lets the browser set the multipart boundary.
      const { data } = await api.post('/api/v1/uploads', fd, { headers: { 'Content-Type': undefined } })
      const item = { url: data.path, type: data.media_type, previewUrl: data.url, poster: null }
      media.value.push(item)

      // A video with no poster shows a black rectangle until it plays. Grab its
      // first frame in the browser and upload that as the poster — no server-side
      // transcoding needed (there's no FFmpeg here anyway).
      if (data.media_type === 'video') {
        capturePoster(file).then((posterPath) => { item.poster = posterPath }).catch(() => {})
      }
    }
  } catch (err) {
    $q.notify({
      color: 'negative', position: 'top',
      message: err.response?.data?.errors?.file?.[0] || 'Upload failed',
    })
  } finally {
    uploading.value = false
  }
}

// ── GIF picker ──────────────────────────────────────
const gifDialog = ref(false)
const gifQuery = ref('')
const gifs = ref([])
const gifLoading = ref(false)
const gifNotice = ref('')

const openGifs = () => {
  gifDialog.value = true
  if (!gifs.value.length) searchGifs()
}

const searchGifs = async () => {
  gifLoading.value = true
  gifNotice.value = ''
  try {
    const { data } = await api.get('/api/v1/media/gifs', { params: { q: gifQuery.value || '' } })
    if (data.configured === false) {
      gifNotice.value = data.message
      gifs.value = []
    } else {
      gifs.value = data.data || []
    }
  } catch (err) {
    gifNotice.value = err.response?.data?.message || 'GIF search is unavailable right now.'
    gifs.value = []
  } finally {
    gifLoading.value = false
  }
}

const pickGif = (gif) => {
  // Giphy GIFs are hotlinked, not copied to our storage.
  media.value.push({ url: gif.url, type: 'gif', previewUrl: gif.url })
  gifDialog.value = false
}

// ── Music picker ────────────────────────────────────
const musicDialog = ref(false)
const musicQuery = ref('')
const tracks = ref([])
const musicLoading = ref(false)
const musicNotice = ref('')
const previewId = ref(null)
let previewAudio = null

// The shelves themselves come from the API so there's one definition of them;
// only the icons live here, since the backend has no business knowing about
// Material icon names. An unrecognised shelf still renders, with a note.
const MOOD_ICONS = {
  popular:   'trending_up',
  chill:     'bedtime',
  cinematic: 'movie',
  roadtrip:  'directions_car',
  adventure: 'hiking',
  acoustic:  'piano',
  desi:      'temple_hindu',
  calm:      'spa',
  folk:      'nature_people',
}

const moods = ref([])
const activeMood = ref('popular')

// "Popular" isn't a Jamendo tag — it's the unfiltered, popularity-ordered
// listing, which is what the endpoint returns when no mood is passed.
const moodShelves = computed(() => [{ slug: 'popular', label: 'Popular' }, ...moods.value])

const openMusic = () => {
  musicDialog.value = true
  if (!tracks.value.length) searchMusic()
}

// A shelf and a search are alternatives, not filters that stack — picking one
// clears the other, so what you see always has one obvious cause.
const pickMood = (slug) => {
  activeMood.value = slug
  musicQuery.value = ''
  searchMusic()
}

const onMusicSearch = () => {
  if (musicQuery.value) activeMood.value = 'popular'
  searchMusic()
}

const searchMusic = async () => {
  musicLoading.value = true
  musicNotice.value = ''
  try {
    const { data } = await api.get('/api/v1/media/music', {
      params: {
        q: musicQuery.value || '',
        ...(!musicQuery.value && activeMood.value !== 'popular' ? { mood: activeMood.value } : {}),
      },
    })
    if (data.moods) moods.value = data.moods
    if (data.configured === false) {
      musicNotice.value = data.message
      tracks.value = []
    } else {
      tracks.value = data.data || []
    }
  } catch (err) {
    musicNotice.value = err.response?.data?.message || 'Music search is unavailable right now.'
    tracks.value = []
  } finally {
    musicLoading.value = false
  }
}

const stopPreview = () => {
  if (previewAudio) {
    previewAudio.pause()
    previewAudio = null
  }
  previewId.value = null
}

const previewTrack = (track) => {
  if (previewId.value === track.id) return stopPreview()
  stopPreview()
  previewAudio = new Audio(track.audio_url)
  previewAudio.addEventListener('ended', stopPreview)
  previewAudio.play().then(() => (previewId.value = track.id)).catch(stopPreview)
}

const pickTrack = (track) => {
  form.audio = {
    provider: track.provider,
    id: track.id,
    title: track.title,
    artist: track.artist,
    audio_url: track.audio_url,
    cover: track.cover,
  }
  stopPreview()
  musicDialog.value = false
}

onUnmounted(stopPreview)

// ── Submit ──────────────────────────────────────────
const reset = () => {
  Object.assign(form, { body: '', type: 'story', destination_id: null, audio: null })
  media.value = []
  if (props.dialog) {
    emit('close')
  } else {
    expanded.value = false
  }
}

const submit = async () => {
  posting.value = true
  try {
    const { data } = await api.post('/api/v1/community/posts', {
      ...form,
      body: form.body.trim(),
      gallery: media.value.map(({ url, type, poster }) => ({ url, type, poster: poster || null })),
    })
    emit('created', data.data)
    reset()
    $q.notify({ color: 'positive', icon: 'check_circle', message: 'Shared', position: 'top' })
  } catch (err) {
    $q.notify({
      color: 'negative', position: 'top',
      message: err.response?.data?.errors?.body?.[0] || err.response?.data?.message || 'Could not post',
    })
  } finally {
    posting.value = false
  }
}
</script>

<style scoped>
.composer {
  background: #fff; border: 1px solid #ece6f0; border-radius: 14px;
  margin-bottom: 12px; overflow: hidden; flex-shrink: 0;
  box-shadow: 0 1px 2px rgba(43, 27, 51, 0.03);
}

.prompt { display: flex; align-items: center; gap: 10px; padding: 9px 12px; cursor: pointer; transition: background 0.15s ease; }
.prompt:hover { background: #fcfafd; }
.prompt-avatar {
  background: linear-gradient(135deg, #7b1fa2, #4a148c);
  color: #fff; font-weight: 700; font-size: 13px; flex-shrink: 0;
}
.prompt-text {
  flex: 1; font-size: 13px; color: #9b8aa5;
  padding: 7px 14px; border-radius: 999px; background: #f7f3fa; border: 1px solid #f0eaf4;
}

.editor { padding: 0; max-height: 70vh; overflow-y: auto; }
.editor-who { display: flex; flex-direction: column; line-height: 1.15; }
.editor-hint { font-size: 11px; color: #9b8aa5; }

/* Inside a dialog the card provides the frame, so drop the extra chrome. */
.composer--dialog { border: 0; border-radius: 0; margin: 0; box-shadow: none; }
.composer--dialog .editor { max-height: 86vh; }
/* Media tiles and pickers can still outgrow the dialog, so the two controls
   that end the flow stay pinned to the bottom of the card. */
.composer--dialog .attach-bar,
.composer--dialog .editor-actions { position: sticky; z-index: 1; }
.composer--dialog .attach-bar { bottom: 54px; }
.composer--dialog .editor-actions { bottom: 0; border-top: 1px solid #f4eff7; }
.editor-head {
  display: flex; align-items: center; gap: 10px;
  position: sticky; top: 0; z-index: 1;
  padding: 12px 14px; border-bottom: 1px solid #f4eff7;
  background: linear-gradient(180deg, #faf7fc, #fff);
}
.editor-name { font-size: 13.5px; font-weight: 600; color: #2b1b33; }

.type-scroll {
  display: flex; gap: 6px; padding: 12px 14px 4px;
  overflow-x: auto; scrollbar-width: none;
}
.type-scroll::-webkit-scrollbar { display: none; }
.type-pill {
  display: inline-flex; align-items: center; gap: 5px; white-space: nowrap;
  padding: 5px 11px; border: 1px solid #e5dced; border-radius: 999px; background: #fff;
  font: inherit; font-size: 12px; color: #6b5a75; cursor: pointer; transition: all 0.15s ease;
}
.type-pill:hover { border-color: #c9b3d6; }
.type-pill--active { background: var(--q-primary); border-color: var(--q-primary); color: #fff; font-weight: 500; }

.body-wrap {
  margin: 8px 14px 0; padding: 4px 12px;
  border: 1px solid #ece6f0; border-radius: 12px; background: #fcfafd;
  transition: border-color 0.15s ease, background 0.15s ease;
}
.body-wrap:focus-within { border-color: #c9b3d6; background: #fff; }
.body-input { font-size: 14px; }
/* Autogrow sets an inline height from scrollHeight, so a long post would push
   Share off the bottom of the screen. max-height beats the inline height; past
   that the caption scrolls inside its own box. */
.body-input :deep(textarea) { max-height: 32vh; overflow-y: auto; }

.gallery-strip {
  display: flex; gap: 8px; margin: 10px 14px 0;
  overflow-x: auto; padding-bottom: 4px; scrollbar-width: none;
}
.gallery-strip::-webkit-scrollbar { display: none; }
.tile {
  position: relative; width: 92px; height: 92px; flex-shrink: 0;
  border-radius: 10px; overflow: hidden; background: #f2eef5; border: 1px solid #ece6f0;
}
.tile-el { width: 100%; height: 100%; object-fit: cover; display: block; }
.tile-kind {
  position: absolute; left: 4px; bottom: 4px; padding: 1px 5px; border-radius: 4px;
  background: rgba(20, 10, 26, 0.7); color: #fff; font-size: 8.5px; font-weight: 700;
}
.tile-x { position: absolute; top: 3px; right: 3px; background: rgba(20, 10, 26, 0.6); color: #fff; }
.tile--add {
  display: flex; align-items: center; justify-content: center;
  border: 1.5px dashed #d9cfe2; background: #fcfafd; color: #9b8aa5; cursor: pointer;
}
.tile--add:hover { border-color: var(--q-primary); color: var(--q-primary); }

.track-chip {
  display: flex; align-items: center; gap: 7px; margin: 10px 14px 0;
  padding: 6px 8px 6px 11px; border-radius: 999px;
  background: linear-gradient(120deg, #f5eef8, #ede7f6); color: #4a148c; font-size: 12.5px;
}
.track-text { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.attach-bar {
  display: flex; align-items: center; gap: 4px; flex-wrap: wrap;
  margin-top: 12px; padding: 8px 14px; border-top: 1px solid #f4eff7; background: #fcfafd;
}
.attach-label {
  font-size: 11px; font-weight: 700; letter-spacing: 0.06em;
  text-transform: uppercase; color: #b0a3b8; margin-right: 4px;
}
.attach {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 7px 10px; border: 0; border-radius: 9px; background: transparent;
  font: inherit; font-size: 12.5px; color: #6b5a75; cursor: pointer; transition: background 0.15s ease;
}
.attach:hover:not(:disabled) { background: #f5eef8; color: #2b1b33; }
.attach:disabled { opacity: 0.5; cursor: not-allowed; }
.attach .q-icon { color: var(--q-primary); }
.counter { font-size: 11.5px; color: #b0a3b8; }

.uploading { display: flex; align-items: center; gap: 9px; padding: 6px 14px; font-size: 12px; color: #7a6a82; }
.uploading .q-linear-progress { flex: 1; }

.editor-actions {
  display: flex; justify-content: flex-end; gap: 8px;
  padding: 10px 14px 12px; background: #fcfafd;
}
.dest-select { margin: 10px 14px 0; }
.hidden { display: none; }

/* ── Pickers ────────────────────────────────────────── */
.picker { width: 560px; max-width: 92vw; border-radius: 14px; }
.picker-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.picker-sub { font-size: 12px; color: #9b8aa5; margin-top: 2px; }
.mood-rail {
  display: flex; gap: 6px; margin-top: 10px;
  overflow-x: auto; scrollbar-width: none; padding-bottom: 2px;
}
.mood-rail::-webkit-scrollbar { display: none; }
.mood-pill {
  display: inline-flex; align-items: center; gap: 5px; flex-shrink: 0;
  padding: 5px 11px; border-radius: 999px; cursor: pointer;
  border: 1px solid #ece6f0; background: #fff;
  font-size: 12px; color: #6b5a75; white-space: nowrap;
  transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}
.mood-pill:hover { border-color: #c9b3d6; background: #fcfafd; }
.mood-pill--active {
  background: linear-gradient(135deg, #7b1fa2, #4a148c);
  border-color: transparent; color: #fff; font-weight: 600;
}

.picker-body { max-height: 52vh; overflow-y: auto; }
.picker-loading { display: flex; justify-content: center; padding: 32px 0; }
.picker-notice {
  display: flex; flex-direction: column; align-items: center; gap: 8px;
  padding: 34px 16px; text-align: center; color: #9b8aa5; font-size: 13px;
}

.gif-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.gif-cell {
  border: 0; padding: 0; border-radius: 8px; overflow: hidden; cursor: pointer;
  background: #f2eef5; aspect-ratio: 1; transition: transform 0.12s ease;
}
.gif-cell:hover { transform: scale(0.97); }
.gif-cell img { width: 100%; height: 100%; object-fit: cover; display: block; }

.audio-cover { background: #4a148c; color: #fff; }
</style>
