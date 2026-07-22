<template>
  <q-page padding class="sub-page">
    <div class="sub-shell">
      <header class="sub-head">
        <span class="page-eyebrow"><q-icon name="workspace_premium" size="12px" />Plans</span>
        <h1 class="page-title">Grow your agency</h1>
        <p class="page-sub">Upgrade for more packages, better placement and real analytics.</p>
      </header>

      <div v-if="loading" class="sub-state"><q-spinner-dots color="primary" size="30px" /></div>

      <template v-else>
        <div v-if="current" class="sub-current">
          <q-icon :name="current.is_active ? 'verified' : 'info'" size="18px" />
          <span v-if="current.is_active">
            You're on <b>{{ cap(current.tier) }}</b> until {{ fmtDate(current.expires_at) }}.
          </span>
          <span v-else-if="current.tier !== 'basic'">
            Your <b>{{ cap(current.tier) }}</b> plan has lapsed — renew to switch the features back on.
          </span>
          <span v-else>You're on the free <b>Basic</b> plan.</span>
        </div>

        <div class="sub-toggle">
          <button
            v-for="p in ['monthly', 'yearly']" :key="p"
            type="button" class="sub-toggle-btn"
            :class="{ 'sub-toggle-btn--active': period === p }"
            @click="period = p"
          >
            {{ cap(p) }}
            <span v-if="p === 'yearly'" class="sub-save">2 months free</span>
          </button>
        </div>

        <div class="sub-grid">
          <article
            v-for="plan in plans" :key="plan.tier"
            class="sub-card" :class="{ 'sub-card--featured': plan.tier === 'premium' }"
          >
            <span v-if="plan.tier === 'premium'" class="sub-ribbon">Most complete</span>

            <div class="sub-tier">{{ cap(plan.tier) }}</div>
            <div class="sub-price">
              <span class="sub-price-n">PKR {{ fmt(plan[period]) }}</span>
              <span class="sub-price-p">/{{ period === 'yearly' ? 'year' : 'month' }}</span>
            </div>
            <div v-if="period === 'yearly'" class="sub-price-save">
              Saves PKR {{ fmt(plan.yearly_saving) }} a year
            </div>

            <ul class="sub-features">
              <li v-for="f in FEATURES[plan.tier]" :key="f">
                <q-icon name="check" size="14px" />{{ f }}
              </li>
            </ul>

            <q-btn
              unelevated rounded no-caps
              :color="plan.tier === 'premium' ? 'deep-purple' : 'grey-8'"
              :outline="plan.tier !== 'premium'"
              class="sub-cta"
              :label="ctaFor(plan.tier)"
              :loading="starting === plan.tier"
              @click="choose(plan)"
            />
          </article>
        </div>

        <div v-if="history.length" class="sub-history">
          <div class="sub-label">Billing history</div>
          <div v-for="h in history" :key="h.id" class="sub-hrow">
            <span class="sub-hmain">{{ cap(h.tier) }} · {{ cap(h.period) }}</span>
            <span class="sub-hdate">{{ h.starts_at ? `${h.starts_at} → ${h.ends_at}` : 'Not started' }}</span>
            <span class="sub-hstatus" :class="`sub-hstatus--${h.status}`">{{ h.status }}</span>
            <span class="sub-hamt">PKR {{ fmt(h.amount) }}</span>
          </div>
        </div>
      </template>
    </div>
  </q-page>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { api } from 'src/boot/axios'

const router = useRouter()
const $q = useQuasar()

const loading = ref(true)
const starting = ref(null)
const plans = ref([])
const current = ref(null)
const history = ref([])
const period = ref('monthly')

// Mirrors the tier table in the business plan — the promise the price makes.
const FEATURES = {
  pro: [
    '15 packages a month',
    'Boosted in search',
    'Basic analytics',
    'Booking management',
    'Direct inbox',
  ],
  premium: [
    'Unlimited packages',
    'Top placement in search',
    'Full analytics',
    'Featured homepage slot',
    'Everything in Pro, plus exports',
  ],
}

const cap = (s) => (s ? s[0].toUpperCase() + s.slice(1) : '')
const fmt = (n) => Number(n || 0).toLocaleString()
const fmtDate = (d) =>
  d ? new Date(d.replace(' ', 'T')).toLocaleDateString('en-PK', {
    day: 'numeric', month: 'short', year: 'numeric',
  }) : '—'

const ctaFor = (tier) => {
  if (!current.value || !current.value.is_active) return `Go ${cap(tier)}`
  if (current.value.tier === tier) return 'Renew'
  return current.value.tier === 'premium' && tier === 'pro' ? 'Switch to Pro' : `Upgrade to ${cap(tier)}`
}

const load = async () => {
  loading.value = true
  try {
    const [p, h] = await Promise.all([
      api.get('/api/v1/subscriptions/plans'),
      api.get('/api/v1/subscriptions/history'),
    ])
    plans.value = p.data.data.plans
    current.value = p.data.data.current
    history.value = h.data.data || []
  } finally {
    loading.value = false
  }
}

onMounted(load)

// Reserve first, pay second: the tier only changes once money clears, so an
// abandoned checkout can't upgrade anyone.
const choose = async (plan) => {
  starting.value = plan.tier
  try {
    const { data } = await api.post('/api/v1/subscriptions', {
      tier: plan.tier,
      period: period.value,
    })
    router.push(`/checkout?type=subscription&id=${data.data.id}`)
  } catch (err) {
    $q.notify({
      color: 'negative',
      message: err.response?.data?.message || "We couldn't start that upgrade.",
      position: 'top',
    })
  } finally {
    starting.value = null
  }
}
</script>

<style scoped>
.sub-page { background: #faf8fc; }
.sub-shell { max-width: 880px; margin: 0 auto; }
.sub-head { margin-bottom: 16px; }
.sub-state { display: grid; place-items: center; padding: 60px 0; }

.sub-current {
  display: flex; align-items: center; gap: 8px;
  padding: 11px 14px; margin-bottom: 16px;
  border-radius: 12px; background: #f7f3fa; border: 1px solid #f0eaf4;
  font-size: 13px; color: #6b5a75;
}

.sub-toggle {
  display: inline-flex; gap: 4px; padding: 4px; margin-bottom: 16px;
  border-radius: 999px; background: #fff; border: 1px solid #ece6f0;
}
.sub-toggle-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 7px 16px; border: 0; border-radius: 999px; cursor: pointer;
  background: none; font-size: 13px; font-weight: 500; color: #6b5a75;
  transition: background 0.15s ease, color 0.15s ease;
}
.sub-toggle-btn--active {
  background: linear-gradient(135deg, #7b1fa2, #4a148c); color: #fff; font-weight: 600;
}
.sub-save {
  padding: 1px 7px; border-radius: 999px; background: #e8f5e9; color: #2e7d32;
  font-size: 9.5px; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase;
}
.sub-toggle-btn--active .sub-save { background: rgba(255, 255, 255, 0.22); color: #fff; }

.sub-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
@media (max-width: 700px) { .sub-grid { grid-template-columns: 1fr; } }

.sub-card {
  position: relative;
  background: #fff; border: 1px solid #ece6f0; border-radius: 16px; padding: 20px 18px;
  display: flex; flex-direction: column;
}
.sub-card--featured {
  border-color: #d3bfe2;
  box-shadow: 0 4px 20px rgba(74, 20, 140, 0.10);
}
.sub-ribbon {
  position: absolute; top: 14px; right: 14px;
  padding: 3px 9px; border-radius: 999px; color: #fff;
  background: linear-gradient(135deg, #7b1fa2, #4a148c);
  font-size: 9.5px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
}

.sub-tier { font-size: 12px; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; color: #a99bb2; }
.sub-price { display: flex; align-items: baseline; gap: 5px; margin-top: 6px; }
.sub-price-n {
  font-size: 27px; font-weight: 700; line-height: 1.1;
  background: linear-gradient(135deg, #4a148c, #7b1fa2);
  -webkit-background-clip: text; background-clip: text; color: transparent;
}
.sub-price-p { font-size: 13px; color: #9b8aa5; }
.sub-price-save { font-size: 11.5px; color: #2e7d32; margin-top: 3px; font-weight: 500; }

.sub-features { list-style: none; padding: 0; margin: 16px 0 18px; flex: 1; }
.sub-features li {
  display: flex; align-items: flex-start; gap: 7px;
  font-size: 13px; color: #4a3d52; padding: 5px 0;
}
.sub-features .q-icon { color: #7b1fa2; margin-top: 2px; flex-shrink: 0; }
.sub-cta { width: 100%; font-weight: 600; }

.sub-history { margin-top: 26px; }
.sub-label {
  font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase;
  color: #a99bb2; margin-bottom: 9px;
}
.sub-hrow {
  display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
  padding: 11px 14px; margin-bottom: 6px;
  background: #fff; border: 1px solid #ece6f0; border-radius: 12px;
  font-size: 12.5px; color: #6b5a75;
}
.sub-hmain { font-weight: 600; color: #2b1b33; }
.sub-hdate { flex: 1; font-size: 11.5px; color: #9b8aa5; }
.sub-hstatus {
  padding: 1px 8px; border-radius: 999px; text-transform: capitalize;
  font-size: 10.5px; font-weight: 600; background: #f3ecf7; color: #6a3f86;
}
.sub-hstatus--active { background: #e8f5e9; color: #2e7d32; }
.sub-hstatus--expired, .sub-hstatus--cancelled { background: #f5f0f8; color: #9b8aa5; }
.sub-hamt { font-weight: 700; color: #2b1b33; }
</style>
