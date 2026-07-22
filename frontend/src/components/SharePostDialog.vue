<template>
  <q-dialog :model-value="modelValue" @update:model-value="close">
    <q-card class="share-card">
      <q-card-section class="share-head">
        <div>
          <div class="text-h6">Send post</div>
          <div class="share-sub">Share this with people you travel with</div>
        </div>
        <q-btn flat round dense icon="close" @click="close" />
      </q-card-section>

      <!-- What's being sent -->
      <q-card-section class="q-pt-none">
        <div class="preview">
          <div class="preview-media">
            <img v-if="post?.media_url && post.media_type !== 'video'" :src="post.media_url" alt="" />
            <q-icon v-else-if="post?.media_type === 'video'" name="movie" size="20px" />
            <q-icon v-else name="article" size="20px" />
          </div>
          <div class="preview-text">
            <span class="preview-author">{{ post?.author?.name }}</span>
            <span class="preview-body">{{ post?.body }}</span>
          </div>
        </div>
      </q-card-section>

      <!-- Recipient search -->
      <q-card-section class="q-pt-none">
        <q-input
          v-model="query"
          outlined dense rounded clearable
          placeholder="Search by name…"
          debounce="300"
          @update:model-value="search"
        >
          <template v-slot:prepend><q-icon name="search" /></template>
        </q-input>

        <!-- Chosen -->
        <div v-if="selected.length" class="chips">
          <span v-for="u in selected" :key="u.id" class="chip">
            <q-avatar size="18px" class="chip-avatar">
              <img v-if="u.avatar" :src="u.avatar" />
              <span v-else>{{ u.name?.[0]?.toUpperCase() }}</span>
            </q-avatar>
            {{ u.name }}
            <q-icon name="close" size="13px" class="chip-x" @click="toggle(u)" />
          </span>
        </div>
      </q-card-section>

      <!-- Results -->
      <q-card-section class="results">
        <div v-if="loading" class="state"><q-spinner-dots color="primary" size="26px" /></div>
        <div v-else-if="!results.length" class="state">
          {{ query ? 'No one found by that name.' : 'Start typing to find someone.' }}
        </div>
        <q-list v-else separator>
          <q-item
            v-for="u in results" :key="u.id"
            clickable
            :active="isSelected(u)"
            active-class="row-selected"
            @click="toggle(u)"
          >
            <q-item-section avatar>
              <q-avatar size="38px" class="result-avatar">
                <img v-if="u.avatar" :src="u.avatar" />
                <span v-else>{{ u.name?.[0]?.toUpperCase() }}</span>
              </q-avatar>
            </q-item-section>
            <q-item-section>
              <q-item-label class="text-weight-medium">
                {{ u.name }}
                <q-icon v-if="u.is_verified" name="verified" size="14px" color="deep-purple" />
              </q-item-label>
              <q-item-label caption>{{ u.city || 'Traveller' }}</q-item-label>
            </q-item-section>
            <q-item-section side>
              <q-icon
                :name="isSelected(u) ? 'check_circle' : 'radio_button_unchecked'"
                :color="isSelected(u) ? 'primary' : 'grey-5'"
                size="21px"
              />
            </q-item-section>
          </q-item>
        </q-list>
      </q-card-section>

      <q-card-section class="q-pt-none">
        <q-input
          v-model="note"
          outlined dense
          placeholder="Add a message (optional)"
          maxlength="500"
        />
      </q-card-section>

      <q-card-actions align="right" class="q-px-md q-pb-md">
        <q-btn flat no-caps color="grey-7" label="Cancel" @click="close" />
        <q-btn
          unelevated rounded no-caps color="primary" icon="send"
          :label="selected.length > 1 ? `Send to ${selected.length}` : 'Send'"
          :disable="!selected.length" :loading="sending"
          @click="send"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { api } from 'src/boot/axios'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  post: { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'sent'])

const $q = useQuasar()

const query = ref('')
const note = ref('')
const results = ref([])
const selected = ref([])
const loading = ref(false)
const sending = ref(false)

const isSelected = (u) => selected.value.some((s) => s.id === u.id)

const toggle = (u) => {
  selected.value = isSelected(u)
    ? selected.value.filter((s) => s.id !== u.id)
    : [...selected.value, u]
}

const search = async () => {
  loading.value = true
  try {
    // /users already excludes blocked people and non-traveller accounts.
    const { data } = await api.get('/api/v1/users', {
      params: { search: query.value || undefined, per_page: 15 },
    })
    results.value = data.data || []
  } catch {
    results.value = []
  } finally {
    loading.value = false
  }
}

const send = async () => {
  sending.value = true
  try {
    const { data } = await api.post(`/api/v1/community/posts/${props.post.id}/share`, {
      user_ids: selected.value.map((u) => u.id),
      note: note.value || null,
    })

    $q.notify({ color: 'positive', icon: 'send', message: data.message, position: 'top' })

    // Someone in a block relationship can't be reached — say so plainly.
    if (data.skipped) {
      $q.notify({
        color: 'grey-8',
        position: 'top',
        message: `${data.skipped} ${data.skipped === 1 ? 'person' : 'people'} couldn't be reached.`,
      })
    }

    emit('sent', data)
    close()
  } catch (err) {
    $q.notify({
      color: 'negative',
      position: 'top',
      message: err.response?.data?.message || 'Could not send this post',
    })
  } finally {
    sending.value = false
  }
}

const close = () => {
  emit('update:modelValue', false)
}

// Reset and preload suggestions each time it opens.
watch(
  () => props.modelValue,
  (open) => {
    if (!open) return
    query.value = ''
    note.value = ''
    selected.value = []
    search()
  },
)
</script>

<style scoped>
.share-card { width: 460px; max-width: 94vw; border-radius: 16px; }
.share-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.share-sub { font-size: 12px; color: #9b8aa5; margin-top: 2px; }

.preview {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 11px; border-radius: 11px;
  background: #faf7fc; border: 1px solid #ece6f0;
}
.preview-media {
  width: 42px; height: 42px; border-radius: 8px; flex-shrink: 0; overflow: hidden;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, #7b1fa2, #4a148c); color: #fff;
}
.preview-media img { width: 100%; height: 100%; object-fit: cover; }
.preview-text { display: flex; flex-direction: column; min-width: 0; }
.preview-author { font-size: 12.5px; font-weight: 600; color: #2b1b33; }
.preview-body {
  font-size: 12px; color: #7a6a82;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

.chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
.chip {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 3px 8px 3px 3px; border-radius: 999px;
  background: #f0e4f7; color: #4a148c; font-size: 12px; font-weight: 500;
}
.chip-avatar { background: #4a148c; color: #fff; font-size: 9px; font-weight: 700; }
.chip-x { cursor: pointer; opacity: 0.6; }
.chip-x:hover { opacity: 1; }

.results { max-height: 38vh; overflow-y: auto; padding-top: 0; }
.state { display: flex; justify-content: center; padding: 26px 0; font-size: 13px; color: #b0a3b8; }
.result-avatar {
  background: linear-gradient(135deg, #7b1fa2, #4a148c);
  color: #fff; font-weight: 700; font-size: 14px;
}
.row-selected { background: #f7f0fa; }
</style>
