<template>
  <q-dialog
    :model-value="modelValue"
    class="report-dialog"
    transition-show="jump-up"
    transition-hide="jump-down"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <q-card class="report-card">
      <header class="report-head">
        <span class="report-mark"><q-icon name="flag" size="19px" /></span>
        <div class="report-title-wrap">
          <div class="report-title">{{ heading }}</div>
          <div class="report-sub">Reports are anonymous — we never say who sent them.</div>
        </div>
        <q-btn flat round dense size="sm" icon="close" color="grey-7" @click="close" />
      </header>

      <div class="report-body">
        <div class="field-label">What's wrong with it?</div>

        <div class="reason-list">
          <button
            v-for="r in reasons"
            :key="r.value"
            type="button"
            class="reason"
            :class="{ 'reason--active': reason === r.value }"
            @click="reason = r.value"
          >
            <span class="reason-icon"><q-icon :name="r.icon" size="17px" /></span>
            <span class="reason-text">
              <span class="reason-label">{{ r.label }}</span>
              <span class="reason-hint">{{ r.hint }}</span>
            </span>
            <q-icon
              :name="reason === r.value ? 'radio_button_checked' : 'radio_button_unchecked'"
              size="18px"
              class="reason-tick"
            />
          </button>
        </div>

        <div class="field-label q-mt-md">
          Anything else? <span class="field-optional">optional</span>
        </div>
        <div class="detail-wrap">
          <q-input
            v-model="description"
            class="detail-input"
            type="textarea"
            borderless
            autogrow
            :rows="3"
            maxlength="500"
            placeholder="Context helps us act faster — what happened, and when?"
          />
        </div>
        <div class="detail-foot">{{ description.length }}/500</div>

        <div v-if="errorMsg" class="report-error">
          <q-icon name="error_outline" size="17px" />
          <span>{{ errorMsg }}</span>
        </div>
      </div>

      <footer class="report-actions">
        <q-btn flat no-caps color="grey-7" label="Cancel" @click="close" />
        <q-btn
          unelevated rounded no-caps
          color="negative"
          label="Submit report"
          :loading="loading"
          :disable="!reason"
          @click="submit"
        />
      </footer>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useQuasar } from 'quasar'
import { useSafetyStore } from 'src/stores/safetyStore'

const props = defineProps({
  modelValue:   { type: Boolean, default: false },
  reportedId:   { type: Number, required: true },
  reportedType: { type: String, required: true }, // 'user' | 'message' | 'package' | 'post'
})
const emit = defineEmits(['update:modelValue', 'reported'])

const $q = useQuasar()
const safetyStore = useSafetyStore()

const reason = ref(null)
const description = ref('')
const loading = ref(false)
const errorMsg = ref('')

const heading = computed(() => ({
  user:    'Report this person',
  message: 'Report this message',
  package: 'Report this package',
  post:    'Report this post',
}[props.reportedType] ?? 'Report this content'))

// The six values are fixed by the API's enum, but not all of them make sense
// everywhere — a post can't be a fake profile, and offering the option only
// makes the form feel careless.
const REASONS = [
  { value: 'inappropriate_content', label: 'Inappropriate content', icon: 'visibility_off', hint: 'Explicit, graphic or offensive' },
  { value: 'harassment',            label: 'Harassment or bullying', icon: 'sentiment_very_dissatisfied', hint: 'Targeting or threatening someone' },
  { value: 'scam',                  label: 'Scam or fraud',          icon: 'gpp_bad',   hint: 'Trying to take money or details' },
  { value: 'spam',                  label: 'Spam',                   icon: 'block',     hint: 'Repetitive or unwanted promotion' },
  { value: 'fake_profile',          label: 'Fake profile',           icon: 'person_off', hint: 'Pretending to be someone else', only: ['user'] },
  { value: 'other',                 label: 'Something else',         icon: 'more_horiz', hint: "Tell us below and we'll look" },
]

const reasons = computed(() =>
  REASONS.filter((r) => !r.only || r.only.includes(props.reportedType)),
)

const close = () => {
  reason.value = null
  description.value = ''
  errorMsg.value = ''
  emit('update:modelValue', false)
}

const submit = async () => {
  if (!reason.value) return
  loading.value = true
  errorMsg.value = ''
  try {
    await safetyStore.reportContent(
      props.reportedId,
      props.reportedType,
      reason.value,
      description.value || null,
    )
    $q.notify({
      color: 'positive',
      icon: 'check_circle',
      message: 'Report sent — thanks for looking out for everyone.',
      position: 'top',
    })
    emit('reported')
    close()
  } catch (err) {
    errorMsg.value = err.response?.data?.message || 'That report didn\'t go through. Try again in a moment.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.report-card {
  width: 440px; max-width: 94vw;
  border-radius: 16px; overflow: hidden;
}

.report-head {
  display: flex; align-items: flex-start; gap: 11px;
  padding: 14px 14px 13px;
  border-bottom: 1px solid #f4eff7;
  background: linear-gradient(180deg, #fdf7f8, #fff);
}
.report-mark {
  display: grid; place-items: center; flex-shrink: 0;
  width: 34px; height: 34px; border-radius: 10px;
  background: #fdeced; color: #c62828;
}
.report-title-wrap { flex: 1; min-width: 0; }
.report-title { font-size: 15px; font-weight: 700; color: #2b1b33; line-height: 1.25; }
.report-sub { font-size: 11.5px; color: #9b8aa5; margin-top: 2px; }

.report-body { padding: 14px; }
.field-label { font-size: 12px; font-weight: 600; color: #6b5a75; margin-bottom: 8px; }
.field-optional { font-weight: 400; color: #b0a3b8; }

.reason-list { display: flex; flex-direction: column; gap: 6px; }
.reason {
  display: flex; align-items: center; gap: 10px; width: 100%;
  padding: 9px 11px; border-radius: 11px; cursor: pointer; text-align: left;
  border: 1px solid #ece6f0; background: #fff;
  transition: border-color 0.15s ease, background 0.15s ease;
}
.reason:hover { border-color: #e0b9bd; background: #fffbfb; }
.reason--active { border-color: #c62828; background: #fdf4f5; }

.reason-icon {
  display: grid; place-items: center; flex-shrink: 0;
  width: 30px; height: 30px; border-radius: 9px;
  background: #f5f0f8; color: #7a6a82;
}
.reason--active .reason-icon { background: #fdeced; color: #c62828; }

.reason-text { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.reason-label { font-size: 13px; font-weight: 600; color: #2b1b33; }
.reason-hint { font-size: 11.5px; color: #9b8aa5; }
.reason-tick { color: #cbbdd4; flex-shrink: 0; }
.reason--active .reason-tick { color: #c62828; }

.detail-wrap {
  padding: 3px 11px;
  border: 1px solid #ece6f0; border-radius: 11px; background: #fcfafd;
  transition: border-color 0.15s ease, background 0.15s ease;
}
.detail-wrap:focus-within { border-color: #c9b3d6; background: #fff; }
.detail-input { font-size: 13px; }
/* Same trap as the post composer: autogrow sets an inline height, so without a
   cap a long report pushes the submit button off the screen. */
.detail-input :deep(textarea) { max-height: 22vh; overflow-y: auto; }
.detail-foot { text-align: right; font-size: 11px; color: #b0a3b8; margin-top: 4px; }

.report-error {
  display: flex; align-items: center; gap: 7px; margin-top: 11px;
  padding: 9px 11px; border-radius: 10px;
  background: #fdeced; border: 1px solid #f8d7da;
  font-size: 12.5px; color: #b3261e;
}

.report-actions {
  display: flex; justify-content: flex-end; gap: 8px;
  padding: 11px 14px 13px;
  border-top: 1px solid #f4eff7; background: #fcfafd;
}
</style>
