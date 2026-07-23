<template>
  <transition name="cf-fade">
    <div v-if="conflicts.length" class="cf">
      <q-icon name="event_busy" size="17px" class="cf-icon" />
      <div class="cf-body">
        <div class="cf-title">
          {{ conflicts.length === 1 ? 'This overlaps a trip you\'re already on' : `This overlaps ${conflicts.length} of your plans` }}
        </div>
        <router-link
          v-for="c in conflicts"
          :key="c.link + c.start_date"
          :to="c.link"
          class="cf-row"
        >
          <q-icon :name="c.kind === 'package' ? 'card_travel' : 'hiking'" size="13px" />
          <span class="cf-name">{{ c.title }}</span>
          <span class="cf-dates">{{ formatRange(c.start_date, c.end_date) }}</span>
        </router-link>
        <div class="cf-foot">You can still book — just make sure you can make both.</div>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useAuthStore } from 'src/stores/authStore'
import { loadCommitments, conflictsWith, formatRange } from 'src/utils/schedule'

const props = defineProps({
  start: { type: String, default: null },
  end: { type: String, default: null },
  // A commitment for the very thing being viewed shouldn't warn against itself.
  ignoreLink: { type: String, default: null },
})

const authStore = useAuthStore()
const conflicts = ref([])

const check = async () => {
  conflicts.value = []
  if (!authStore.isLoggedIn || !props.start || !props.end) return
  const commitments = await loadCommitments()
  conflicts.value = conflictsWith(commitments, props.start, props.end)
    .filter((c) => c.link !== props.ignoreLink)
}

watch(() => [props.start, props.end], check, { immediate: true })
</script>

<style scoped>
.cf {
  display: flex; gap: 9px;
  padding: 11px 12px; border-radius: 12px;
  background: #fff8ec; border: 1px solid #f6e2b8;
}
.cf-icon { color: #d98a00; flex-shrink: 0; margin-top: 1px; }
.cf-body { flex: 1; min-width: 0; }
.cf-title { font-size: 12.5px; font-weight: 700; color: #9a6a00; }
.cf-row {
  display: flex; align-items: center; gap: 6px;
  margin-top: 5px; padding: 4px 7px; border-radius: 8px;
  background: #fffdf7; border: 1px solid #f3e6c4;
  font-size: 12px; color: #6b5a33; text-decoration: none;
  transition: background 0.14s ease;
}
.cf-row:hover { background: #fdf3dd; }
.cf-row .q-icon { color: #b6862a; flex-shrink: 0; }
.cf-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 500; }
.cf-dates { color: #a08a52; flex-shrink: 0; }
.cf-foot { font-size: 11px; color: #b09456; margin-top: 6px; }

.cf-fade-enter-active, .cf-fade-leave-active { transition: opacity 0.2s ease; }
.cf-fade-enter-from, .cf-fade-leave-to { opacity: 0; }
</style>
