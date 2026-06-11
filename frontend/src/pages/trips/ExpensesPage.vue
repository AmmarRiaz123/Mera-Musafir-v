<template>
  <q-page padding>
    <!-- Header -->
    <div class="row items-center q-mb-lg">
      <q-btn flat round dense icon="arrow_back" @click="$router.back()" />
      <div class="text-h5 text-weight-bold q-ml-sm">Expenses</div>
      <q-space />
      <q-btn color="teal" unelevated rounded icon="add" label="Add" @click="openAddExpense" />
    </div>

    <!-- Loading -->
    <div v-if="store.loading" class="row justify-center q-py-xl">
      <q-spinner-dots color="teal" size="3em" />
    </div>

    <template v-else>
      <!-- Total banner -->
      <q-banner v-if="store.expenses.length" rounded class="bg-teal-1 q-mb-lg">
        <template v-slot:avatar><q-icon name="account_balance_wallet" color="teal-8" size="sm" /></template>
        <span class="text-body2">Total spent: </span>
        <span class="text-weight-bold text-teal-9 text-body1">PKR {{ fmt(totalSpent) }}</span>
      </q-banner>

      <!-- Expenses list -->
      <div v-if="store.expenses.length === 0" class="text-center q-py-xl">
        <q-icon name="receipt_long" size="5em" color="grey-3" />
        <div class="text-h6 text-grey-5 q-mt-md">No expenses yet</div>
        <div class="text-body2 text-grey-5 q-mt-xs">Track who paid what for the trip</div>
        <q-btn class="q-mt-lg" color="teal" unelevated rounded label="Add Expense" icon="add" @click="openAddExpense" />
      </div>

      <div v-else>
        <q-card v-for="expense in store.expenses" :key="expense.id" flat bordered class="q-mb-sm">
          <q-card-section class="q-py-sm">
            <div class="row items-start justify-between">
              <div class="row items-center q-gutter-sm">
                <q-avatar size="36px" color="teal-2" text-color="teal-9">
                  <img v-if="expense.paid_by.avatar" :src="expense.paid_by.avatar" />
                  <span v-else class="text-caption text-weight-bold">{{ initial(expense.paid_by.name) }}</span>
                </q-avatar>
                <div>
                  <div class="text-weight-bold">{{ expense.description }}</div>
                  <div class="text-caption text-grey-6">Paid by {{ expense.paid_by.name }}</div>
                </div>
              </div>
              <div class="text-right">
                <div class="text-weight-bold text-teal-8">PKR {{ fmt(expense.amount) }}</div>
                <q-badge :color="expense.split_type === 'equal' ? 'teal-2' : 'orange-2'"
                  :text-color="expense.split_type === 'equal' ? 'teal-9' : 'orange-9'"
                  class="text-caption q-mt-xs">
                  {{ expense.split_type }}
                </q-badge>
              </div>
            </div>

            <!-- Shares -->
            <div class="q-mt-sm q-ml-xl" v-if="expense.shares.length">
              <div v-for="share in expense.shares" :key="share.id" class="row items-center justify-between q-py-xs">
                <div class="row items-center q-gutter-xs">
                  <q-icon :name="share.is_settled ? 'check_circle' : 'radio_button_unchecked'"
                    :color="share.is_settled ? 'positive' : 'grey-5'" size="14px" />
                  <span class="text-caption" :class="share.is_settled ? 'text-grey-5' : ''">{{ share.user.name }}</span>
                </div>
                <span class="text-caption" :class="share.is_settled ? 'text-grey-5 line-through' : 'text-weight-medium'">
                  PKR {{ fmt(share.share_amount) }}
                </span>
              </div>
            </div>
          </q-card-section>
        </q-card>
      </div>

      <!-- Settlement section -->
      <div v-if="store.settlement.length" class="q-mt-xl">
        <div class="text-subtitle1 text-weight-bold q-mb-sm">
          <q-icon name="swap_horiz" color="teal" class="q-mr-xs" />Settlements
        </div>
        <q-card v-for="s in store.settlement" :key="`${s.from.id}-${s.to.id}`" flat bordered class="q-mb-sm">
          <q-card-section class="q-py-sm row items-center justify-between">
            <div>
              <span class="text-weight-bold">{{ s.from.name }}</span>
              <span class="text-grey-6 text-caption"> owes </span>
              <span class="text-weight-bold">{{ s.to.name }}</span>
              <div class="text-teal-8 text-weight-bold text-body1">PKR {{ fmt(s.amount) }}</div>
            </div>
            <q-btn
              flat dense rounded color="positive" icon="check_circle" label="Settled"
              @click="markSettled(s)"
              :loading="settlingKey === `${s.from.id}-${s.to.id}`"
              size="sm"
            />
          </q-card-section>
        </q-card>
      </div>
      <div v-else-if="store.expenses.length" class="q-mt-xl text-center">
        <q-icon name="check_circle" color="positive" size="2em" />
        <div class="text-body2 text-positive q-mt-xs">All settled up!</div>
      </div>
    </template>

    <!-- Add Expense dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 360px; max-width: 500px">
        <q-card-section>
          <div class="text-h6">Add Expense</div>
        </q-card-section>
        <q-card-section class="column q-gutter-sm">
          <q-input v-model="form.description" label="Description *" outlined dense autofocus />
          <q-input v-model.number="form.amount" label="Amount (PKR) *" outlined dense type="number" min="1" />

          <q-select
            v-model="form.paid_by_id"
            :options="memberOptions"
            option-value="id"
            option-label="name"
            emit-value
            map-options
            label="Paid by *"
            outlined
            dense
          />

          <q-select
            v-model="form.split_type"
            :options="[{ label: 'Split Equally', value: 'equal' }, { label: 'Custom Split', value: 'custom' }]"
            option-value="value"
            option-label="label"
            emit-value
            map-options
            label="Split type"
            outlined
            dense
          />

          <!-- Custom split inputs -->
          <div v-if="form.split_type === 'custom'" class="q-pl-sm">
            <div class="text-caption text-grey-7 q-mb-xs">Enter each member's share:</div>
            <div v-for="member in tripMembers" :key="member.id" class="row items-center q-gutter-sm q-mb-xs">
              <div class="col text-body2">{{ member.name }}</div>
              <q-input
                v-model.number="customShares[member.id]"
                dense outlined type="number" min="0"
                class="col"
                style="max-width: 120px"
                label="PKR"
              />
            </div>
            <div class="text-caption q-mt-xs" :class="customTotal === form.amount ? 'text-positive' : 'text-negative'">
              Total: PKR {{ fmt(customTotal) }} / {{ fmt(form.amount || 0) }}
            </div>
          </div>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn
            flat color="teal" label="Add Expense"
            :loading="submitting"
            :disable="!form.description || !form.amount || !form.paid_by_id"
            @click="submitExpense"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useQuasar } from 'quasar'
import { usePlanningStore } from 'src/stores/planningStore'
import { useTripStore } from 'src/stores/tripStore'

const route = useRoute()
const $q = useQuasar()
const store = usePlanningStore()
const tripStore = useTripStore()
const tripId = route.params.id

const showDialog = ref(false)
const submitting = ref(false)
const settlingKey = ref(null)

const defaultForm = () => ({ description: '', amount: null, paid_by_id: null, split_type: 'equal' })
const form = ref(defaultForm())
const customShares = ref({})

const tripMembers = computed(() => tripStore.currentTrip?.members || [])
const memberOptions = computed(() => tripMembers.value.map((m) => ({ id: m.id, name: m.name })))

const totalSpent = computed(() => store.expenses.reduce((sum, e) => sum + e.amount, 0))

const customTotal = computed(() =>
  Object.values(customShares.value).reduce((s, v) => s + (Number(v) || 0), 0)
)

onMounted(async () => {
  store.expenses = []
  store.settlement = []
  await Promise.all([
    store.fetchExpenses(tripId),
    tripStore.currentTrip?.id === parseInt(tripId) ? Promise.resolve() : tripStore.fetchTrip(tripId),
  ])
  store.subscribeToPlanning(tripId)
})

onUnmounted(() => store.unsubscribeFromPlanning(tripId))

const openAddExpense = () => {
  form.value = defaultForm()
  customShares.value = Object.fromEntries(tripMembers.value.map((m) => [m.id, 0]))
  showDialog.value = true
}

const submitExpense = async () => {
  submitting.value = true
  try {
    const payload = { ...form.value }
    if (form.value.split_type === 'custom') {
      payload.shares = tripMembers.value.map((m) => ({
        user_id:      m.id,
        share_amount: Number(customShares.value[m.id]) || 0,
      }))
    }
    await store.addExpense(tripId, payload)
    showDialog.value = false
  } catch (err) {
    $q.notify({ color: 'negative', message: err.response?.data?.message || 'Failed to add expense', icon: 'error' })
  } finally {
    submitting.value = false
  }
}

const markSettled = async (settlement) => {
  const key = `${settlement.from.id}-${settlement.to.id}`
  settlingKey.value = key
  try {
    // Find the first unsettled share from settlement.from to settlement.to
    for (const expense of store.expenses) {
      if (expense.paid_by.id !== settlement.to.id) continue
      const share = expense.shares.find((s) => s.user.id === settlement.from.id && !s.is_settled)
      if (share) {
        await store.settleShare(tripId, share.id)
        break
      }
    }
  } catch {
    $q.notify({ color: 'negative', message: 'Failed to settle', icon: 'error' })
  } finally {
    settlingKey.value = null
  }
}

const initial = (name) => (name ? name[0].toUpperCase() : '?')
const fmt = (n) => Number(n || 0).toLocaleString()
</script>

<style scoped>
.line-through { text-decoration: line-through; }
</style>
