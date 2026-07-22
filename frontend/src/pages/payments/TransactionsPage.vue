<template>
  <q-page padding class="tx-page">
    <div class="tx-shell">
      <header class="tx-head">
        <div>
          <span class="page-eyebrow"><q-icon name="receipt_long" size="12px" />Money</span>
          <h1 class="page-title">Transactions</h1>
          <p class="page-sub">Everything you've paid for on Mera Musafir.</p>
        </div>
        <div v-if="settledTotal" class="tx-total">
          <span class="tx-total-l">Total paid</span>
          <span class="tx-total-n">PKR {{ fmt(settledTotal) }}</span>
        </div>
      </header>

      <div v-if="loading" class="tx-state"><q-spinner-dots color="primary" size="30px" /></div>

      <div v-else-if="!items.length" class="tx-empty">
        <q-icon name="account_balance_wallet" size="40px" />
        <div class="tx-empty-title">No payments yet</div>
        <p class="tx-empty-text">
          Book an agency package and the receipt will show up here.
        </p>
        <q-btn unelevated rounded no-caps color="deep-purple" label="Browse packages" to="/packages" />
      </div>

      <div v-else class="tx-list">
        <article v-for="p in items" :key="p.id" class="tx-row">
          <span class="tx-icon" :class="`tx-icon--${p.status}`">
            <q-icon :name="statusIcon(p.status)" size="18px" />
          </span>

          <div class="tx-main">
            <div class="tx-title-line">
              <span class="tx-title">{{ p.for.title }}</span>
              <span class="tx-badge" :class="`tx-badge--${p.status}`">{{ statusLabel(p.status) }}</span>
            </div>
            <div class="tx-meta">
              <span v-if="p.for.meta">{{ p.for.meta }}</span>
              <span class="tx-dot">·</span>
              <span>{{ gatewayLabel(p.gateway) }}</span>
              <span class="tx-dot">·</span>
              <span>{{ fmtDate(p.paid_at || p.created_at) }}</span>
            </div>
            <div v-if="p.failure_reason" class="tx-fail">{{ p.failure_reason }}</div>
            <div class="tx-ref">{{ p.reference }}</div>
          </div>

          <div class="tx-amount">
            <span class="tx-amount-n" :class="{ 'tx-amount-n--void': p.status !== 'succeeded' }">
              PKR {{ fmt(p.amount) }}
            </span>
            <!-- Agencies care what actually lands with them after our cut. -->
            <span v-if="p.commission > 0 && p.status === 'succeeded'" class="tx-fee">
              incl. PKR {{ fmt(p.commission) }} platform fee
            </span>
          </div>
        </article>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { api } from 'src/boot/axios'

const items = ref([])
const loading = ref(true)

const settledTotal = computed(() =>
  items.value.filter((p) => p.status === 'succeeded').reduce((n, p) => n + p.amount, 0),
)

const fmt = (n) => Number(n || 0).toLocaleString()
const fmtDate = (d) =>
  d ? new Date(d.replace(' ', 'T')).toLocaleDateString('en-PK', {
    day: 'numeric', month: 'short', year: 'numeric',
  }) : '—'

const LABELS = { jazzcash: 'JazzCash', easypaisa: 'EasyPaisa', stripe: 'Card', sandbox: 'Test payment' }
const gatewayLabel = (g) => LABELS[g] ?? g

const statusIcon = (s) => ({
  succeeded: 'check', failed: 'close', refunded: 'undo', pending: 'schedule',
}[s] ?? 'payments')
const statusLabel = (s) => ({
  succeeded: 'Paid', failed: 'Failed', refunded: 'Refunded', pending: 'Pending',
}[s] ?? s)

onMounted(async () => {
  try {
    const { data } = await api.get('/api/v1/payments')
    items.value = data.data || []
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.tx-page { background: #faf8fc; }
.tx-shell { max-width: 780px; margin: 0 auto; }

.tx-head {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 16px; flex-wrap: wrap; margin-bottom: 18px;
}
.tx-total {
  display: flex; flex-direction: column; align-items: flex-end;
  padding: 9px 14px; border-radius: 12px;
  background: #fff; border: 1px solid #ece6f0;
}
.tx-total-l { font-size: 10.5px; letter-spacing: 0.05em; text-transform: uppercase; color: #a99bb2; }
.tx-total-n { font-size: 17px; font-weight: 700; color: #4a148c; }

.tx-state { display: grid; place-items: center; padding: 60px 0; }

.tx-list { display: flex; flex-direction: column; gap: 8px; }
.tx-row {
  display: flex; align-items: flex-start; gap: 12px;
  background: #fff; border: 1px solid #ece6f0; border-radius: 14px; padding: 13px 14px;
  transition: box-shadow 0.15s ease;
}
.tx-row:hover { box-shadow: 0 3px 14px rgba(43, 27, 51, 0.07); }

.tx-icon {
  display: grid; place-items: center; width: 34px; height: 34px; flex-shrink: 0;
  border-radius: 10px; background: #f3ecf7; color: #6a3f86;
}
.tx-icon--succeeded { background: #e8f5e9; color: #2e7d32; }
.tx-icon--failed    { background: #fdeced; color: #c62828; }
.tx-icon--refunded  { background: #fff4e5; color: #ef6c00; }

.tx-main { flex: 1; min-width: 0; }
.tx-title-line { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
.tx-title { font-size: 14px; font-weight: 600; color: #2b1b33; }
.tx-badge {
  padding: 1px 7px; border-radius: 999px;
  font-size: 9.5px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
}
.tx-badge--succeeded { background: #e8f5e9; color: #2e7d32; }
.tx-badge--failed    { background: #fdeced; color: #c62828; }
.tx-badge--refunded  { background: #fff4e5; color: #ef6c00; }
.tx-badge--pending   { background: #f3ecf7; color: #6a3f86; }

.tx-meta {
  display: flex; align-items: center; gap: 5px; flex-wrap: wrap;
  font-size: 11.5px; color: #9b8aa5; margin-top: 2px;
}
.tx-dot { opacity: 0.55; }
.tx-fail { font-size: 11.5px; color: #b3261e; margin-top: 3px; }
.tx-ref {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 10.5px; color: #b0a3b8; margin-top: 4px;
}

.tx-amount { text-align: right; flex-shrink: 0; }
.tx-amount-n { font-size: 14.5px; font-weight: 700; color: #2b1b33; }
.tx-amount-n--void { color: #b0a3b8; text-decoration: line-through; }
.tx-fee { display: block; font-size: 10.5px; color: #a99bb2; margin-top: 2px; }

.tx-empty {
  display: flex; flex-direction: column; align-items: center; gap: 8px;
  padding: 60px 24px; text-align: center; color: #b0a3b8;
  background: #fff; border: 1px solid #ece6f0; border-radius: 16px;
}
.tx-empty-title { font-size: 15px; font-weight: 600; color: #6b5a75; }
.tx-empty-text { font-size: 12.5px; max-width: 34ch; margin: 0 0 6px; line-height: 1.5; }
</style>
