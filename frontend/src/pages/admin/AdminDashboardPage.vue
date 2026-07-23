<template>
  <q-page class="admin-page">
    <header class="ap-head">
      <div>
        <h1 class="ap-title">Dashboard</h1>
        <p class="ap-sub">How the platform is doing today.</p>
      </div>
    </header>

    <div v-if="loading" class="ap-loading"><q-spinner-dots color="deep-purple" size="34px" /></div>

    <template v-else-if="stats">
      <div class="ad-metrics">
        <div class="ad-metric">
          <span class="ad-metric-ic ad-metric-ic--a"><q-icon name="group" size="20px" /></span>
          <div class="ad-metric-body">
            <span class="ad-metric-n">{{ fmt(stats.users.total) }}</span>
            <span class="ad-metric-l">Travellers</span>
            <span class="ad-metric-sub">+{{ stats.users.new_today }} today</span>
          </div>
        </div>

        <div class="ad-metric">
          <span class="ad-metric-ic ad-metric-ic--b"><q-icon name="business" size="20px" /></span>
          <div class="ad-metric-body">
            <span class="ad-metric-n">{{ fmt(stats.users.agencies) }}</span>
            <span class="ad-metric-l">Agencies</span>
          </div>
        </div>

        <div class="ad-metric">
          <span class="ad-metric-ic ad-metric-ic--c"><q-icon name="hiking" size="20px" /></span>
          <div class="ad-metric-body">
            <span class="ad-metric-n">{{ fmt(stats.trips.active) }}</span>
            <span class="ad-metric-l">Active trips</span>
          </div>
        </div>

        <div class="ad-metric">
          <span class="ad-metric-ic ad-metric-ic--d"><q-icon name="payments" size="20px" /></span>
          <div class="ad-metric-body">
            <span class="ad-metric-n">PKR {{ fmt(stats.revenue.commission_month) }}</span>
            <span class="ad-metric-l">Commission this month</span>
            <span class="ad-metric-sub">PKR {{ fmt(stats.revenue.gross_month) }} processed</span>
          </div>
        </div>
      </div>

      <div class="ad-queues">
        <router-link to="/admin/reports" class="ad-queue" :class="{ 'ad-queue--alert': stats.queues.open_reports }">
          <span class="ad-queue-ic"><q-icon name="flag" size="22px" /></span>
          <div>
            <div class="ad-queue-n">{{ stats.queues.open_reports }}</div>
            <div class="ad-queue-l">Open reports</div>
          </div>
          <q-icon name="chevron_right" size="20px" class="ad-queue-go" />
        </router-link>

        <router-link to="/admin/agencies" class="ad-queue" :class="{ 'ad-queue--alert': stats.queues.pending_verification }">
          <span class="ad-queue-ic"><q-icon name="verified" size="22px" /></span>
          <div>
            <div class="ad-queue-n">{{ stats.queues.pending_verification }}</div>
            <div class="ad-queue-l">Agencies awaiting verification</div>
          </div>
          <q-icon name="chevron_right" size="20px" class="ad-queue-go" />
        </router-link>
      </div>

      <section class="ad-revenue">
        <div class="ad-section-title">Revenue — last 6 months</div>
        <div v-if="!revenue.length" class="ad-empty">No settled payments yet.</div>
        <div v-else class="ad-rev-list">
          <div v-for="m in revenueByMonth" :key="m.month" class="ad-rev-row">
            <span class="ad-rev-month">{{ prettyMonth(m.month) }}</span>
            <div class="ad-rev-bar-track">
              <div class="ad-rev-bar" :style="{ width: barWidth(m.gross) + '%' }" />
            </div>
            <span class="ad-rev-amt">PKR {{ fmt(m.gross) }}</span>
            <span class="ad-rev-comm">+{{ fmt(m.commission) }} fee</span>
          </div>
        </div>
      </section>
    </template>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { api } from 'src/boot/axios'

const emit = defineEmits(['queues'])

const loading = ref(true)
const stats = ref(null)
const revenue = ref([])

const fmt = (n) => Number(n || 0).toLocaleString()

// The revenue endpoint splits by gateway; the dashboard bar wants the month.
const revenueByMonth = computed(() => {
  const acc = {}
  for (const r of revenue.value) {
    acc[r.month] ??= { month: r.month, gross: 0, commission: 0 }
    acc[r.month].gross += Number(r.gross)
    acc[r.month].commission += Number(r.commission)
  }
  return Object.values(acc).sort((a, b) => a.month.localeCompare(b.month))
})

const maxGross = computed(() => Math.max(1, ...revenueByMonth.value.map((m) => m.gross)))
const barWidth = (g) => Math.round((g / maxGross.value) * 100)

const prettyMonth = (ym) => {
  const [y, m] = ym.split('-')
  return new Date(y, m - 1).toLocaleDateString('en-PK', { month: 'short', year: '2-digit' })
}

onMounted(async () => {
  try {
    const [d, r] = await Promise.all([
      api.get('/api/v1/admin/dashboard'),
      api.get('/api/v1/admin/revenue'),
    ])
    stats.value = d.data.data
    revenue.value = r.data.data || []
    emit('queues', stats.value.queues)
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
@import 'src/css/admin.scss';

.ad-metrics {
  display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 16px;
}
@media (max-width: 900px) { .ad-metrics { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 520px) { .ad-metrics { grid-template-columns: 1fr; } }

.ad-metric {
  display: flex; align-items: center; gap: 13px;
  background: #fff; border: 1px solid #e7e4f0; border-radius: 15px; padding: 16px;
  box-shadow: 0 2px 10px rgba(43, 27, 51, 0.04);
}
.ad-metric-ic {
  display: grid; place-items: center; width: 42px; height: 42px; flex-shrink: 0;
  border-radius: 12px; color: #fff;
}
.ad-metric-ic--a { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }
.ad-metric-ic--b { background: linear-gradient(135deg, #f59e0b, #d97706); }
.ad-metric-ic--c { background: linear-gradient(135deg, #10b981, #059669); }
.ad-metric-ic--d { background: linear-gradient(135deg, #ec4899, #be185d); }
.ad-metric-body { display: flex; flex-direction: column; min-width: 0; }
.ad-metric-n { font-size: 21px; font-weight: 800; color: #241636; line-height: 1.15; }
.ad-metric-l { font-size: 12px; color: #7c6f90; }
.ad-metric-sub { font-size: 11px; color: #10b981; margin-top: 1px; }

.ad-queues { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px; }
@media (max-width: 700px) { .ad-queues { grid-template-columns: 1fr; } }
.ad-queue {
  display: flex; align-items: center; gap: 13px; text-decoration: none;
  background: #fff; border: 1px solid #e7e4f0; border-radius: 15px; padding: 16px;
  transition: box-shadow 0.15s ease, transform 0.15s ease;
}
.ad-queue:hover { box-shadow: 0 5px 16px rgba(43, 27, 51, 0.08); transform: translateY(-1px); }
.ad-queue-ic {
  display: grid; place-items: center; width: 42px; height: 42px; flex-shrink: 0;
  border-radius: 12px; background: #efeaf9; color: #6d28d9;
}
.ad-queue--alert .ad-queue-ic { background: #fdecec; color: #dc2626; }
.ad-queue-n { font-size: 22px; font-weight: 800; color: #241636; line-height: 1; }
.ad-queue--alert .ad-queue-n { color: #dc2626; }
.ad-queue-l { font-size: 12.5px; color: #7c6f90; margin-top: 2px; }
.ad-queue-go { margin-left: auto; color: #c3b9d6; }

.ad-revenue {
  background: #fff; border: 1px solid #e7e4f0; border-radius: 15px; padding: 18px;
}
.ad-rev-list { display: flex; flex-direction: column; gap: 10px; }
.ad-rev-row { display: flex; align-items: center; gap: 12px; }
.ad-rev-month { width: 56px; font-size: 12px; font-weight: 600; color: #5a4d6e; flex-shrink: 0; }
.ad-rev-bar-track { flex: 1; height: 9px; border-radius: 999px; background: #f0edf7; overflow: hidden; }
.ad-rev-bar { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #8b5cf6, #6d28d9); }
.ad-rev-amt { width: 110px; text-align: right; font-size: 12.5px; font-weight: 600; color: #241636; flex-shrink: 0; }
.ad-rev-comm { width: 90px; text-align: right; font-size: 11px; color: #10b981; flex-shrink: 0; }
.ad-empty { padding: 20px; text-align: center; color: #a99bb2; font-size: 13px; }
</style>
