<template>
  <q-dialog :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)">
    <q-card style="min-width: 320px; max-width: 420px">
      <q-card-section>
        <div class="text-h6">Report Content</div>
        <div class="text-caption text-grey-6">Our team reviews all reports within 24 hours</div>
      </q-card-section>

      <q-card-section class="q-pt-none q-gutter-md">
        <q-select
          v-model="reason"
          :options="reasonOptions"
          option-value="value"
          option-label="label"
          emit-value
          map-options
          outlined
          label="Reason *"
          :rules="[val => !!val || 'Please select a reason']"
        />

        <q-input
          v-model="description"
          type="textarea"
          outlined
          label="Additional details (optional)"
          rows="3"
          autogrow
          :rules="[val => !val || val.length <= 500 || 'Max 500 characters']"
        />

        <div v-if="errorMsg" class="text-negative text-caption">{{ errorMsg }}</div>
      </q-card-section>

      <q-card-actions align="right">
        <q-btn flat label="Cancel" @click="close" />
        <q-btn
          unelevated
          color="negative"
          label="Submit Report"
          :loading="loading"
          :disable="!reason"
          @click="submit"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { ref } from 'vue'
import { useQuasar } from 'quasar'
import { useSafetyStore } from 'src/stores/safetyStore'

const props = defineProps({
  modelValue:   { type: Boolean, default: false },
  reportedId:   { type: Number, required: true },
  reportedType: { type: String, required: true }, // 'user' | 'message' | 'package'
})
const emit = defineEmits(['update:modelValue', 'reported'])

const $q = useQuasar()
const safetyStore = useSafetyStore()

const reason = ref(null)
const description = ref('')
const loading = ref(false)
const errorMsg = ref('')

const reasonOptions = [
  { label: 'Spam',                   value: 'spam' },
  { label: 'Harassment',             value: 'harassment' },
  { label: 'Inappropriate Content',  value: 'inappropriate_content' },
  { label: 'Fake Profile',           value: 'fake_profile' },
  { label: 'Scam',                   value: 'scam' },
  { label: 'Other',                  value: 'other' },
]

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
    $q.notify({ color: 'positive', icon: 'check_circle', message: 'Report submitted' })
    emit('reported')
    close()
  } catch (err) {
    errorMsg.value = err.response?.data?.message || 'Failed to submit report'
  } finally {
    loading.value = false
  }
}
</script>
