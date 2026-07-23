<template>
  <q-page class="admin-page">
    <header class="ap-head">
      <div>
        <h1 class="ap-title">Reports</h1>
        <p class="ap-sub">Review what travellers flagged, and close it out.</p>
      </div>
    </header>

    <div class="ap-filters">
      <div class="ap-seg">
        <button
          v-for="s in tabs" :key="s.value" type="button"
          class="ap-seg-btn" :class="{ 'ap-seg-btn--active': status === s.value }"
          @click="setStatus(s.value)"
        >{{ s.label }}</button>
      </div>
    </div>

    <div v-if="loading" class="ap-loading"><q-spinner-dots color="deep-purple" size="30px" /></div>

    <div v-else-if="!reports.length" class="ap-card">
      <div class="ap-empty">
        <q-icon name="task_alt" size="36px" />
        <div class="ap-empty-title">{{ status === 'pending' ? 'The queue is clear' : 'Nothing here' }}</div>
      </div>
    </div>

    <div v-else class="rp-list">
      <article v-for="r in reports" :key="r.id" class="rp-card" :class="`rp-card--${r.subject.kind}`">
        <div class="rp-head">
          <span class="rp-reason" :class="`rp-reason--${r.reason}`">{{ reasonLabel(r.reason) }}</span>
          <span class="rp-kind"><q-icon :name="kindIcon(r.subject.kind)" size="13px" />{{ r.subject.kind }}</span>
          <span v-if="r.status !== 'pending'" class="rp-status" :class="`rp-status--${r.status}`">{{ r.status }}</span>
          <span class="rp-time">{{ timeAgo(r.created_at) }}</span>
        </div>

        <div class="rp-subject">
          <span class="rp-subject-label" :class="{ 'rp-gone': r.subject.gone }">{{ r.subject.label }}</span>
          <q-btn
            v-if="r.subject.link" flat dense no-caps size="sm" color="deep-purple"
            icon="open_in_new" label="Open" :to="r.subject.link" target="_blank"
          />
          <span v-else-if="r.subject.gone" class="rp-deleted">already removed</span>
        </div>

        <p v-if="r.description" class="rp-desc">“{{ r.description }}”</p>

        <div class="rp-foot">
          <span class="rp-reporter">
            <q-avatar size="22px" class="rp-rep-avatar">
              <img v-if="r.reporter?.avatar" :src="r.reporter.avatar" />
              <span v-else>{{ r.reporter?.name?.[0]?.toUpperCase() }}</span>
            </q-avatar>
            reported by {{ r.reporter?.name ?? 'someone' }}
          </span>

          <div v-if="r.status === 'pending'" class="rp-actions">
            <span v-if="r.offender?.is_suspended" class="rp-susp-tag">
              <q-icon name="block" size="12px" />{{ firstName(r.offender.name) }} suspended
            </span>
            <q-btn flat no-caps dense size="sm" color="grey-7" label="Dismiss" :loading="acting === r.id" @click="openResolve(r, 'dismiss')" />
            <q-btn
              v-if="canSuspend(r)"
              unelevated rounded no-caps dense size="sm" color="negative"
              :label="`Suspend ${firstName(r.offender.name)}`"
              icon="block"
              :loading="acting === r.id" @click="openResolve(r, 'suspend')"
            />
            <q-btn
              v-else
              flat no-caps dense size="sm" color="deep-purple"
              label="Mark actioned"
              :loading="acting === r.id" @click="openResolve(r, 'actioned')"
            />
          </div>
          <span v-else-if="r.admin_note" class="rp-note">Note: {{ r.admin_note }}</span>
        </div>
      </article>
    </div>

    <div v-if="meta.last_page > 1" class="ap-pager">
      <q-btn flat round dense icon="chevron_left" :disable="page <= 1" @click="go(page - 1)" />
      <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <q-btn flat round dense icon="chevron_right" :disable="page >= meta.last_page" @click="go(page + 1)" />
    </div>

    <!-- Resolve dialog: capture an optional note -->
    <q-dialog v-model="resolveOpen" class="admin-resolve-dialog">
      <q-card class="rr-card">
        <div class="rr-head">
          <q-icon :name="dialogMeta.icon" size="18px" />
          <span>{{ dialogMeta.title }}</span>
        </div>
        <div class="rr-body">
          <p class="rr-lead">{{ dialogMeta.lead }}</p>
          <q-input
            v-model="note" type="textarea" outlined autogrow :rows="2" :maxlength="1000"
            placeholder="Add a note for the record (optional)…"
          />
        </div>
        <div class="rr-actions">
          <q-btn flat no-caps color="grey-7" label="Cancel" v-close-popup />
          <q-btn
            unelevated rounded no-caps
            :color="dialogMeta.color"
            :label="dialogMeta.cta"
            :loading="acting === pending.id"
            @click="submitResolve"
          />
        </div>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useQuasar } from 'quasar'
import { api } from 'src/boot/axios'

const emit = defineEmits(['queues'])
const $q = useQuasar()

const tabs = [
  { value: 'pending', label: 'Open' },
  { value: 'actioned', label: 'Actioned' },
  { value: 'dismissed', label: 'Dismissed' },
  { value: 'all', label: 'All' },
]

const reports = ref([])
const meta = ref({ current_page: 1, last_page: 1 })
const loading = ref(true)
const acting = ref(null)
const status = ref('pending')
const page = ref(1)

const resolveOpen = ref(false)
const pending = reactive({ id: null, action: null, offenderId: null })
const note = ref('')
const dialogMeta = computed(() => DIALOGS[pending.action] ?? DIALOGS.dismiss)

const REASONS = {
  spam: 'Spam', harassment: 'Harassment', inappropriate_content: 'Inappropriate',
  fake_profile: 'Fake profile', scam: 'Scam', other: 'Other',
}
const reasonLabel = (r) => REASONS[r] ?? r
const kindIcon = (k) => ({ user: 'person', post: 'photo', package: 'card_travel', message: 'mail' }[k] ?? 'flag')

const firstName = (name) => (name || 'them').split(' ')[0]

// Suspend is offered only when there's a real, non-admin account behind the
// report that isn't already suspended.
const canSuspend = (r) => r.offender && !r.offender.is_admin && !r.offender.is_suspended

// The resolve dialog wears three hats: suspend the offender, mark handled, or
// dismiss. Each maps to the API status and a suspend flag in submitResolve.
const DIALOGS = {
  suspend: {
    icon: 'block', title: 'Suspend & close', color: 'negative', cta: 'Suspend',
    lead: 'Suspends the account behind this report platform-wide and closes the report as actioned. You can reinstate them later from Users.',
  },
  actioned: {
    icon: 'gavel', title: 'Mark as actioned', color: 'deep-purple', cta: 'Confirm',
    lead: 'Record that you handled this — removed the content, warned them, or dealt with it elsewhere.',
  },
  dismiss: {
    icon: 'do_not_disturb_on', title: 'Dismiss report', color: 'deep-purple', cta: 'Dismiss',
    lead: 'Close this report as not a violation. The reporter is told it was reviewed.',
  },
}

const load = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/admin/reports', { params: { status: status.value, page: page.value } })
    reports.value = data.data
    meta.value = data.meta
  } finally {
    loading.value = false
  }
}

const setStatus = (v) => { status.value = v; page.value = 1; load() }
const go = (p) => { page.value = p; load() }

const openResolve = (report, action) => {
  pending.id = report.id
  pending.action = action
  pending.offenderId = report.offender?.id ?? null
  note.value = ''
  resolveOpen.value = true
}

const submitResolve = async () => {
  acting.value = pending.id
  try {
    const map = {
      suspend:  { status: 'actioned', suspend: true },
      actioned: { status: 'actioned', suspend: false },
      dismiss:  { status: 'dismissed', suspend: false },
    }[pending.action] ?? { status: 'dismissed', suspend: false }

    await api.post(`/api/v1/admin/reports/${pending.id}/resolve`, {
      ...map,
      note: note.value || null,
    })
    resolveOpen.value = false
    $q.notify({
      color: 'positive', icon: 'check_circle', position: 'top',
      message: map.suspend ? 'Suspended and report closed.' : 'Report closed.',
    })

    // Suspending one report's offender applies to every other open report
    // against the same person — reflect that so their other cards flip from
    // "Suspend" to "Mark actioned" without a reload.
    if (map.suspend && pending.offenderId) {
      reports.value.forEach((r) => {
        if (r.offender?.id === pending.offenderId) r.offender.is_suspended = true
      })
    }

    // Drop the resolved one from the open queue and refresh the badge.
    if (status.value === 'pending') {
      reports.value = reports.value.filter((r) => r.id !== pending.id)
    } else {
      load()
    }
    refreshBadge()
  } catch {
    $q.notify({ color: 'negative', message: 'Could not update that report.', position: 'top' })
  } finally {
    acting.value = null
  }
}

const refreshBadge = async () => {
  try {
    const { data } = await api.get('/api/v1/admin/dashboard')
    emit('queues', data.data.queues)
  } catch { /* badge stays */ }
}

const timeAgo = (iso) => {
  const s = Math.floor((Date.now() - new Date(iso.replace(' ', 'T'))) / 1000)
  if (s < 3600) return `${Math.max(1, Math.floor(s / 60))}m`
  if (s < 86400) return `${Math.floor(s / 3600)}h`
  return `${Math.floor(s / 86400)}d`
}

onMounted(load)
</script>

<style scoped>
@import 'src/css/admin.scss';

.rp-list { display: flex; flex-direction: column; gap: 12px; }
.rp-card {
  background: #fff; border: 1px solid #e7e4f0; border-radius: 14px; padding: 15px 16px;
  border-left: 3px solid #c3b9d6;
}
.rp-card--user { border-left-color: #ef4444; }
.rp-card--post { border-left-color: #f59e0b; }
.rp-card--package { border-left-color: #8b5cf6; }

.rp-head { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
.rp-reason {
  padding: 2px 9px; border-radius: 999px; font-size: 10.5px; font-weight: 700;
  letter-spacing: 0.03em; text-transform: uppercase; background: #fdecec; color: #dc2626;
}
.rp-reason--spam { background: #fef3c7; color: #b45309; }
.rp-reason--other { background: #f0edf7; color: #6b5a75; }
.rp-kind {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 11px; color: #8b7ea6; text-transform: capitalize;
}
.rp-status {
  padding: 1px 8px; border-radius: 999px; font-size: 9.5px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.03em;
}
.rp-status--actioned { background: #fdecec; color: #dc2626; }
.rp-status--dismissed { background: #eef1f5; color: #6b7280; }
.rp-status--reviewed { background: #ede9fb; color: #6d28d9; }
.rp-time { margin-left: auto; font-size: 11px; color: #a99bb2; }

.rp-subject { display: flex; align-items: center; gap: 10px; margin-top: 10px; }
.rp-subject-label { font-size: 14px; font-weight: 600; color: #241636; }
.rp-gone { color: #a99bb2; text-decoration: line-through; }
.rp-deleted { font-size: 11.5px; color: #a99bb2; }

.rp-desc {
  margin: 9px 0 0; padding: 9px 11px; border-radius: 10px;
  background: #faf9fd; border: 1px solid #f0edf7;
  font-size: 12.5px; color: #5a4d6e; line-height: 1.45;
}

.rp-foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 12px; flex-wrap: wrap; }
.rp-reporter { display: flex; align-items: center; gap: 6px; font-size: 11.5px; color: #8b7ea6; }
.rp-rep-avatar { background: #efe4f6; color: #6a3f86; font-weight: 700; font-size: 10px; }
.rp-actions { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.rp-susp-tag {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 2px 8px; border-radius: 999px;
  background: #fdecec; color: #dc2626;
  font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em;
}
.rp-note { font-size: 11.5px; color: #7c6f90; font-style: italic; }

.rr-card { width: 420px; max-width: 94vw; border-radius: 15px; overflow: hidden; }
.rr-head {
  display: flex; align-items: center; gap: 8px;
  padding: 14px 16px; border-bottom: 1px solid #f0edf7;
  font-size: 15px; font-weight: 700; color: #241636;
}
.rr-body { padding: 15px 16px; }
.rr-lead { margin: 0 0 11px; font-size: 12.5px; color: #6b5a75; line-height: 1.45; }
.rr-actions {
  display: flex; justify-content: flex-end; gap: 8px;
  padding: 12px 16px 14px; border-top: 1px solid #f0edf7; background: #faf9fd;
}
</style>
