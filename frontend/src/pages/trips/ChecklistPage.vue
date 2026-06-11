<template>
  <q-page padding>
    <!-- Header -->
    <div class="row items-center q-mb-lg">
      <q-btn flat round dense icon="arrow_back" @click="$router.back()" />
      <div class="text-h5 text-weight-bold q-ml-sm">Packing List</div>
      <q-space />
      <q-btn color="orange-8" unelevated rounded icon="add" label="Add Item" @click="showDialog = true" />
    </div>

    <!-- Loading -->
    <div v-if="store.loading" class="row justify-center q-py-xl">
      <q-spinner-dots color="orange" size="3em" />
    </div>

    <template v-else>
      <!-- Progress bar -->
      <div v-if="store.checklist.length" class="q-mb-lg">
        <div class="row items-center justify-between text-caption text-grey-7 q-mb-xs">
          <span>{{ completedCount }} / {{ store.checklist.length }} items packed</span>
          <span class="text-weight-bold" :class="progress === 1 ? 'text-positive' : 'text-orange-8'">
            {{ Math.round(progress * 100) }}%
          </span>
        </div>
        <q-linear-progress
          :value="progress"
          :color="progress === 1 ? 'positive' : 'orange'"
          rounded
          size="10px"
          track-color="orange-1"
        />
      </div>

      <!-- Empty state -->
      <div v-if="store.checklist.length === 0" class="text-center q-py-xl">
        <q-icon name="checklist" size="5em" color="grey-3" />
        <div class="text-h6 text-grey-5 q-mt-md">Nothing on the list yet</div>
        <div class="text-body2 text-grey-5 q-mt-xs">Add items you need to pack</div>
        <q-btn class="q-mt-lg" color="orange-8" unelevated rounded label="Add First Item" icon="add" @click="showDialog = true" />
      </div>

      <!-- Checklist -->
      <q-list v-else bordered separator class="rounded-borders">
        <q-item
          v-for="item in store.checklist"
          :key="item.id"
          class="q-py-sm"
          :class="item.is_completed ? 'bg-green-1' : ''"
        >
          <q-item-section avatar>
            <q-checkbox
              :model-value="item.is_completed"
              @update:model-value="toggle(item)"
              color="orange-8"
              keep-color
            />
          </q-item-section>
          <q-item-section>
            <q-item-label :class="item.is_completed ? 'text-grey-5 text-strikethrough' : 'text-weight-medium'">
              {{ item.title }}
            </q-item-label>
            <q-item-label caption v-if="item.assigned_to" class="row items-center q-gutter-xs">
              <q-avatar size="16px" color="orange-2" text-color="orange-9">
                <img v-if="item.assigned_to.avatar" :src="item.assigned_to.avatar" />
                <span v-else style="font-size:9px; font-weight:bold">{{ item.assigned_to.name[0] }}</span>
              </q-avatar>
              <span>{{ item.assigned_to.name }}</span>
            </q-item-label>
          </q-item-section>
          <q-item-section side>
            <q-btn flat round dense icon="delete" size="sm" color="negative" @click="remove(item.id)" />
          </q-item-section>
        </q-item>
      </q-list>

      <!-- All done banner -->
      <div v-if="progress === 1 && store.checklist.length" class="text-center q-mt-lg">
        <q-icon name="celebration" size="2.5em" color="positive" />
        <div class="text-h6 text-positive q-mt-xs">All packed!</div>
      </div>
    </template>

    <!-- Add Item dialog -->
    <q-dialog v-model="showDialog" persistent>
      <q-card style="min-width: 320px">
        <q-card-section>
          <div class="text-h6">Add Item</div>
        </q-card-section>
        <q-card-section class="column q-gutter-sm">
          <q-input v-model="form.title" label="Item *" outlined dense autofocus />
          <q-select
            v-model="form.assigned_to_id"
            :options="[{ id: null, name: 'No assignment' }, ...memberOptions]"
            option-value="id"
            option-label="name"
            emit-value
            map-options
            label="Assign to (optional)"
            outlined
            dense
            clearable
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup @click="resetForm" />
          <q-btn
            flat color="orange-8" label="Add"
            :loading="submitting"
            :disable="!form.title"
            @click="submit"
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
const form = ref({ title: '', assigned_to_id: null })

const tripMembers = computed(() => tripStore.currentTrip?.members || [])
const memberOptions = computed(() => tripMembers.value.map((m) => ({ id: m.id, name: m.name })))

const completedCount = computed(() => store.checklist.filter((i) => i.is_completed).length)
const progress = computed(() =>
  store.checklist.length ? completedCount.value / store.checklist.length : 0
)

onMounted(async () => {
  store.checklist = []
  await Promise.all([
    store.fetchChecklist(tripId),
    tripStore.currentTrip?.id === parseInt(tripId) ? Promise.resolve() : tripStore.fetchTrip(tripId),
  ])
  store.subscribeToPlanning(tripId)
})

onUnmounted(() => store.unsubscribeFromPlanning(tripId))

const resetForm = () => { form.value = { title: '', assigned_to_id: null } }

const submit = async () => {
  if (!form.value.title) return
  submitting.value = true
  try {
    await store.addChecklistItem(tripId, {
      title:          form.value.title,
      assigned_to_id: form.value.assigned_to_id || null,
    })
    showDialog.value = false
    resetForm()
  } catch (err) {
    $q.notify({ color: 'negative', message: err.response?.data?.message || 'Failed', icon: 'error' })
  } finally {
    submitting.value = false
  }
}

const toggle = async (item) => {
  try {
    await store.toggleChecklistItem(tripId, item.id)
  } catch {
    $q.notify({ color: 'negative', message: 'Failed to update item', icon: 'error' })
  }
}

const remove = async (itemId) => {
  try {
    await store.deleteChecklistItem(tripId, itemId)
  } catch {
    $q.notify({ color: 'negative', message: 'Failed to delete item', icon: 'error' })
  }
}
</script>

<style scoped>
.text-strikethrough { text-decoration: line-through; }
</style>
