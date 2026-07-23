<template>
  <q-page class="admin-page">
    <header class="ap-head">
      <div>
        <h1 class="ap-title">Broadcast</h1>
        <p class="ap-sub">Send an announcement straight to people's notifications.</p>
      </div>
    </header>

    <div class="bc-grid">
      <section class="bc-form">
        <div class="bc-label">Who should get it?</div>
        <div class="bc-segments">
          <button
            v-for="s in segments" :key="s.value" type="button"
            class="bc-segment" :class="{ 'bc-segment--active': form.segment === s.value }"
            @click="form.segment = s.value"
          >
            <q-icon :name="s.icon" size="20px" />
            <span class="bc-seg-label">{{ s.label }}</span>
            <span class="bc-seg-hint">{{ s.hint }}</span>
          </button>
        </div>

        <div class="bc-label q-mt-lg">Title</div>
        <q-input
          v-model="form.title" outlined dense counter maxlength="120"
          placeholder="e.g. New payout schedule"
        />

        <div class="bc-label q-mt-md">Message <span class="bc-optional">optional</span></div>
        <q-input
          v-model="form.body" type="textarea" outlined autogrow :rows="3" counter maxlength="300"
          placeholder="A short line of detail…"
        />

        <div class="bc-label q-mt-md">Link <span class="bc-optional">optional</span></div>
        <q-input
          v-model="form.link" outlined dense
          placeholder="/packages or /subscription"
        >
          <template #prepend><q-icon name="link" size="18px" /></template>
        </q-input>

        <q-btn
          class="bc-send" unelevated rounded no-caps color="deep-purple"
          icon="campaign" :label="`Send to ${segmentLabel}`"
          :loading="sending" :disable="!form.title.trim()"
          @click="confirmSend"
        />
      </section>

      <aside class="bc-preview">
        <div class="bc-label">Preview</div>
        <div class="bc-note-card">
          <span class="bc-note-ic"><q-icon name="campaign" size="16px" /></span>
          <div class="bc-note-body">
            <div class="bc-note-title">{{ form.title || 'Your announcement title' }}</div>
            <div v-if="form.body" class="bc-note-text">{{ form.body }}</div>
            <div class="bc-note-time">just now</div>
          </div>
        </div>
        <p class="bc-preview-hint">
          <q-icon name="info" size="13px" />
          It lands in the bell and lights the notification badge — the same as any other alert.
        </p>
      </aside>
    </div>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useQuasar } from 'quasar'
import { api } from 'src/boot/axios'

const $q = useQuasar()

const segments = [
  { value: 'all', label: 'Everyone', hint: 'All active accounts', icon: 'groups' },
  { value: 'travelers', label: 'Travellers', hint: 'Traveller accounts', icon: 'hiking' },
  { value: 'agencies', label: 'Agencies', hint: 'Agency accounts', icon: 'business' },
]

const form = reactive({ segment: 'all', title: '', body: '', link: '' })
const sending = ref(false)

const segmentLabel = computed(() => segments.find((s) => s.value === form.segment)?.label ?? '')

const confirmSend = () => {
  $q.dialog({
    title: 'Send this announcement?',
    message: `It'll go out to ${segmentLabel.value.toLowerCase()} as a notification. This can't be unsent.`,
    cancel: { flat: true, noCaps: true, color: 'grey-7', label: 'Cancel' },
    ok: { unelevated: true, rounded: true, noCaps: true, color: 'deep-purple', label: 'Send' },
  }).onOk(send)
}

const send = async () => {
  sending.value = true
  try {
    const { data } = await api.post('/api/v1/admin/broadcast', {
      segment: form.segment,
      title: form.title,
      body: form.body || null,
      link: form.link || null,
    })
    $q.notify({ color: 'positive', icon: 'check_circle', message: data.message, position: 'top' })
    form.title = ''
    form.body = ''
    form.link = ''
  } catch (err) {
    $q.notify({ color: 'negative', message: err.response?.data?.message || 'Could not send that.', position: 'top' })
  } finally {
    sending.value = false
  }
}
</script>

<style scoped>
@import 'src/css/admin.scss';

.bc-grid { display: grid; grid-template-columns: 1.4fr 1fr; gap: 18px; align-items: start; }
@media (max-width: 820px) { .bc-grid { grid-template-columns: 1fr; } }

.bc-form, .bc-preview {
  background: #fff; border: 1px solid #e7e4f0; border-radius: 15px; padding: 18px;
}
.bc-label {
  font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase;
  color: #a99bb2; margin-bottom: 9px;
}
.bc-optional { font-weight: 400; text-transform: none; letter-spacing: 0; color: #c3b9d6; }

.bc-segments { display: flex; gap: 8px; }
.bc-segment {
  flex: 1; display: flex; flex-direction: column; align-items: center; gap: 3px;
  padding: 13px 8px; border-radius: 12px; cursor: pointer;
  border: 1px solid #e7e4f0; background: #fff; color: #6f6285;
  transition: border-color 0.14s ease, background 0.14s ease, color 0.14s ease;
}
.bc-segment:hover { border-color: #c9b3d6; }
.bc-segment--active {
  border-color: #7c3aed; background: #f6f2fe; color: #6d28d9;
}
.bc-seg-label { font-size: 13px; font-weight: 600; }
.bc-seg-hint { font-size: 10.5px; color: #a99bb2; }

.bc-send { width: 100%; margin-top: 20px; font-weight: 600; }

.bc-note-card {
  display: flex; gap: 10px; padding: 12px;
  border-radius: 12px; border: 1px solid #e7e4f0; border-left: 3px solid #7c3aed;
  background: #faf9fd;
}
.bc-note-ic {
  display: grid; place-items: center; width: 32px; height: 32px; flex-shrink: 0;
  border-radius: 10px; color: #fff; background: linear-gradient(135deg, #7c3aed, #6d28d9);
}
.bc-note-title { font-size: 13px; font-weight: 600; color: #241636; }
.bc-note-text { font-size: 12px; color: #6b5a75; margin-top: 1px; }
.bc-note-time { font-size: 11px; color: #a99bb2; margin-top: 3px; }
.bc-preview-hint {
  display: flex; align-items: flex-start; gap: 6px;
  margin: 12px 0 0; font-size: 11.5px; color: #8b7ea6; line-height: 1.45;
}
</style>
