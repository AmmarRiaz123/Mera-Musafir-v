<template>
  <q-page padding class="checkout-page">
    <div class="co-shell">
      <div v-if="loading" class="co-state"><q-spinner-dots color="primary" size="32px" /></div>

      <template v-else-if="settled">
        <!-- ── Receipt ───────────────────────────────────── -->
        <section class="co-card co-receipt">
          <div class="co-tick"><q-icon name="check" size="30px" /></div>
          <h1 class="co-done">Payment complete</h1>
          <p class="co-done-sub">{{ doneBlurb }}</p>

          <div class="co-lines">
            <div class="co-line"><span>Reference</span><b class="co-mono">{{ payment.reference }}</b></div>
            <div class="co-line"><span>{{ payment.for.title }}</span><b>{{ payment.for.meta }}</b></div>
            <div class="co-line"><span>Paid with</span><b>{{ gatewayLabel(payment.gateway) }}</b></div>
            <div class="co-line"><span>Paid on</span><b>{{ fmtDate(payment.paid_at) }}</b></div>
            <div class="co-line co-line--total"><span>Total</span><b>PKR {{ fmt(payment.amount) }}</b></div>
          </div>

          <div class="co-actions">
            <q-btn flat no-caps color="grey-7" icon="receipt_long" label="All transactions" to="/transactions" />
            <q-btn
              unelevated rounded no-caps color="deep-purple"
              :icon="isBooking ? 'luggage' : 'dashboard'"
              :label="isBooking ? 'My bookings' : 'Back to dashboard'"
              :to="isBooking ? '/my-trips?tab=packages' : '/agencies'"
            />
          </div>
        </section>
      </template>

      <template v-else>
        <!-- ── Pay ───────────────────────────────────────── -->
        <header class="co-head">
          <span class="page-eyebrow"><q-icon name="lock" size="12px" />Secure checkout</span>
          <h1 class="page-title">Pay for your {{ isBooking ? 'trip' : 'plan' }}</h1>
        </header>

        <div class="co-grid">
          <section class="co-card">
            <div class="co-label">How would you like to pay?</div>

            <div v-if="!methods.length" class="co-none">
              <q-icon name="credit_card_off" size="26px" />
              <div>No payment methods are switched on yet.</div>
            </div>

            <div v-else class="co-methods">
              <button
                v-for="m in methods"
                :key="m.name"
                type="button"
                class="co-method"
                :class="{ 'co-method--active': gateway === m.name }"
                @click="gateway = m.name"
              >
                <span class="co-method-icon"><q-icon :name="iconFor(m.name)" size="19px" /></span>
                <span class="co-method-text">
                  <span class="co-method-label">{{ m.label }}</span>
                  <span class="co-method-blurb">{{ m.blurb }}</span>
                </span>
                <q-icon
                  :name="gateway === m.name ? 'radio_button_checked' : 'radio_button_unchecked'"
                  size="18px" class="co-method-tick"
                />
              </button>
            </div>

            <div v-if="gateway === 'sandbox'" class="co-note">
              <q-icon name="science" size="14px" />
              <span>
                Test mode — no real money moves. Real gateways switch on once the
                merchant accounts exist.
              </span>
            </div>

            <div v-if="error" class="co-error">
              <q-icon name="error_outline" size="17px" /><span>{{ error }}</span>
            </div>

            <q-btn
              class="co-pay"
              unelevated rounded no-caps size="md"
              color="deep-purple"
              :label="`Pay PKR ${fmt(amount)}`"
              :loading="paying"
              :disable="!gateway"
              @click="pay()"
            />

            <button
              v-if="gateway === 'sandbox'"
              type="button" class="co-decline" :disabled="paying" @click="pay('fail')"
            >
              Simulate a declined payment
            </button>
          </section>

          <aside class="co-card co-summary">
            <div class="co-label">You're paying for</div>
            <div class="co-item">
              <div class="co-item-title">{{ title }}</div>
              <div class="co-item-meta">{{ meta }}</div>
            </div>
            <div class="co-lines">
              <div class="co-line"><span>Subtotal</span><b>PKR {{ fmt(amount) }}</b></div>
              <div class="co-line co-line--total"><span>Total</span><b>PKR {{ fmt(amount) }}</b></div>
            </div>
            <p class="co-fineprint">
              <q-icon name="verified_user" size="13px" />
              Your seat is held once payment clears.
            </p>
          </aside>
        </div>
      </template>
    </div>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useQuasar } from 'quasar'
import { api } from 'src/boot/axios'

const route = useRoute()
const $q = useQuasar()

const type = route.query.type === 'subscription' ? 'subscription' : 'booking'
const payableId = Number(route.query.id)

const loading = ref(true)
const paying = ref(false)
const error = ref('')
const methods = ref([])
const gateway = ref(null)
const payment = ref(null)
const amount = ref(0)
const title = ref('')
const meta = ref('')

const isBooking = computed(() => type === 'booking')
const settled = computed(() => payment.value?.status === 'succeeded')
const doneBlurb = computed(() =>
  isBooking.value
    ? "Your seat is booked and you've been added to the trip's group chat."
    : 'Your plan is active — the new limits apply straight away.',
)

const fmt = (n) => Number(n || 0).toLocaleString()
const fmtDate = (d) =>
  d ? new Date(d.replace(' ', 'T')).toLocaleString('en-PK', {
    day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
  }) : '—'

const LABELS = { jazzcash: 'JazzCash', easypaisa: 'EasyPaisa', stripe: 'Card', sandbox: 'Test payment' }
const gatewayLabel = (g) => LABELS[g] ?? g
const iconFor = (g) => ({
  jazzcash: 'account_balance_wallet',
  easypaisa: 'account_balance_wallet',
  stripe: 'credit_card',
  sandbox: 'science',
}[g] ?? 'payments')

onMounted(async () => {
  try {
    const [m, subject] = await Promise.all([
      api.get('/api/v1/payments/methods'),
      loadSubject(),
    ])
    methods.value = m.data.data || []
    // Preselect the only option rather than making them tap it.
    if (methods.value.length) gateway.value = methods.value[0].name
    void subject
  } catch {
    error.value = "We couldn't load this checkout. Try again from your bookings."
  } finally {
    loading.value = false
  }
})

const loadSubject = async () => {
  if (isBooking.value) {
    const { data } = await api.get('/api/v1/bookings/my')
    const booking = (data.data || []).find((b) => b.id === payableId)
    if (!booking) throw new Error('not found')
    amount.value = booking.total_amount
    title.value = booking.package?.title ?? 'Package booking'
    meta.value = `${booking.travelers_count} ${booking.travelers_count === 1 ? 'traveller' : 'travellers'}`
    return
  }

  const { data } = await api.get('/api/v1/subscriptions/history')
  const sub = (data.data || []).find((s) => s.id === payableId)
  if (!sub) throw new Error('not found')
  amount.value = sub.amount
  title.value = `${sub.tier[0].toUpperCase()}${sub.tier.slice(1)} plan`
  meta.value = sub.period === 'yearly' ? 'Billed yearly' : 'Billed monthly'
}

const pay = async (simulate = null) => {
  paying.value = true
  error.value = ''
  try {
    const { data } = await api.post('/api/v1/payments/initiate', {
      type, id: payableId, gateway: gateway.value, ...(simulate ? { simulate } : {}),
    })
    payment.value = data.data

    // Wallets hand the browser off to their own hosted page.
    if (data.redirect) {
      window.location.href = data.redirect
      return
    }
    $q.notify({ color: 'positive', icon: 'check_circle', message: data.message, position: 'top' })
  } catch (err) {
    const d = err.response?.data
    payment.value = d?.data ?? null
    error.value = d?.message || 'That payment did not go through.'
  } finally {
    paying.value = false
  }
}
</script>

<style scoped>
.checkout-page { background: #faf8fc; }
.co-shell { max-width: 900px; margin: 0 auto; }
.co-state { display: grid; place-items: center; padding: 80px 0; }

.co-head { margin-bottom: 18px; }

.co-grid { display: grid; grid-template-columns: 1.35fr 1fr; gap: 16px; align-items: start; }
@media (max-width: 780px) { .co-grid { grid-template-columns: 1fr; } }

.co-card {
  background: #fff; border: 1px solid #ece6f0; border-radius: 16px; padding: 18px;
  box-shadow: 0 2px 12px rgba(43, 27, 51, 0.05);
}
.co-label {
  font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase;
  color: #a99bb2; margin-bottom: 11px;
}

.co-methods { display: flex; flex-direction: column; gap: 8px; }
.co-method {
  display: flex; align-items: center; gap: 11px; width: 100%; text-align: left;
  padding: 12px; border-radius: 12px; cursor: pointer;
  border: 1px solid #ece6f0; background: #fff;
  transition: border-color 0.15s ease, background 0.15s ease;
}
.co-method:hover { border-color: #c9b3d6; background: #fcfafd; }
.co-method--active { border-color: #7b1fa2; background: #f9f4fc; }
.co-method-icon {
  display: grid; place-items: center; width: 34px; height: 34px;
  border-radius: 10px; background: #f3ecf7; color: #6a3f86; flex-shrink: 0;
}
.co-method--active .co-method-icon { background: #7b1fa2; color: #fff; }
.co-method-text { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.co-method-label { font-size: 13.5px; font-weight: 600; color: #2b1b33; }
.co-method-blurb { font-size: 11.5px; color: #9b8aa5; }
.co-method-tick { color: #cbbdd4; }
.co-method--active .co-method-tick { color: #7b1fa2; }

.co-note, .co-error {
  display: flex; align-items: flex-start; gap: 7px; margin-top: 12px;
  padding: 9px 11px; border-radius: 10px; font-size: 11.5px; line-height: 1.45;
}
.co-note { background: #f7f3fa; border: 1px solid #f0eaf4; color: #8a7a93; }
.co-error { background: #fdeced; border: 1px solid #f8d7da; color: #b3261e; font-size: 12.5px; }

.co-pay { width: 100%; margin-top: 14px; font-weight: 600; }
.co-decline {
  display: block; width: 100%; margin-top: 9px;
  border: 0; background: none; cursor: pointer;
  font-size: 11.5px; color: #b0a3b8; text-decoration: underline;
}
.co-decline:hover { color: #c62828; }

.co-item { padding-bottom: 12px; border-bottom: 1px solid #f4eff7; margin-bottom: 12px; }
.co-item-title { font-size: 14px; font-weight: 600; color: #2b1b33; }
.co-item-meta { font-size: 12px; color: #9b8aa5; margin-top: 2px; }

.co-lines { display: flex; flex-direction: column; gap: 8px; }
.co-line {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  font-size: 13px; color: #7a6a82;
}
.co-line b { color: #2b1b33; font-weight: 600; }
.co-line--total {
  border-top: 1px solid #f4eff7; padding-top: 9px; margin-top: 2px; font-size: 14.5px;
}
.co-line--total b { color: #4a148c; font-size: 16px; }
.co-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12.5px; }

.co-fineprint {
  display: flex; align-items: center; gap: 5px;
  margin: 14px 0 0; font-size: 11.5px; color: #a99bb2;
}

.co-receipt { text-align: center; max-width: 480px; margin: 30px auto 0; }
.co-tick {
  display: grid; place-items: center; width: 58px; height: 58px; margin: 4px auto 12px;
  border-radius: 50%; color: #fff;
  background: linear-gradient(135deg, #43a047, #2e7d32);
  box-shadow: 0 4px 14px rgba(46, 125, 50, 0.28);
}
.co-done {
  font-size: 22px; font-weight: 700; margin: 0;
  background: linear-gradient(135deg, #4a148c, #7b1fa2);
  -webkit-background-clip: text; background-clip: text; color: transparent;
}
.co-done-sub { font-size: 13px; color: #7a6a82; margin: 6px 0 18px; }
.co-receipt .co-lines { text-align: left; margin-bottom: 18px; }
.co-actions { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }

.co-none {
  display: flex; flex-direction: column; align-items: center; gap: 7px;
  padding: 26px; text-align: center; font-size: 12.5px; color: #b0a3b8;
}
</style>
