<template>
  <q-dialog
    :model-value="modelValue"
    class="join-dialog"
    transition-show="jump-up"
    transition-hide="jump-down"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <q-card v-if="trip" class="jt-card">
      <header class="jt-head">
        <q-avatar rounded size="46px" class="jt-thumb">
          <img v-if="trip.cover_image" :src="trip.cover_image" />
          <q-icon v-else name="hiking" size="22px" color="deep-purple" />
        </q-avatar>
        <div class="jt-head-text">
          <div class="jt-title">{{ wasRemoved ? 'Request to rejoin' : (isRequest ? 'Request to join' : 'Join this trip') }}</div>
          <div class="jt-sub">{{ trip.title }}</div>
        </div>
        <q-btn flat round dense size="sm" icon="close" color="grey-7" v-close-popup />
      </header>

      <div class="jt-body">
        <div class="jt-facts">
          <div class="jt-fact">
            <q-icon name="event" size="15px" />
            <span>{{ formatRange(trip.start_date, trip.end_date) }}</span>
          </div>
          <div v-if="trip.destination" class="jt-fact">
            <q-icon name="place" size="15px" />
            <span>{{ trip.destination.name }}</span>
          </div>
          <div class="jt-fact">
            <q-icon name="group" size="15px" />
            <span>{{ trip.spots_left }} spot{{ trip.spots_left !== 1 ? 's' : '' }} left</span>
          </div>
        </div>

        <ConflictNotice
          class="jt-conflict"
          :start="trip.start_date" :end="trip.end_date"
          :ignore-link="`/trips/${trip.id}`"
        />

        <p class="jt-note">
          <q-icon :name="(isRequest || wasRemoved) ? 'lock_clock' : 'groups'" size="13px" />
          <span v-if="wasRemoved">
            The host removed you from this trip before, so they'll need to approve you back in.
          </span>
          <span v-else-if="isRequest">
            The host reviews each request, so you're in once they approve you.
          </span>
          <span v-else>
            You'll join the group chat and planning tools straight away.
          </span>
        </p>
      </div>

      <footer class="jt-actions">
        <q-btn flat no-caps color="grey-7" label="Cancel" v-close-popup />
        <q-btn
          unelevated rounded no-caps color="primary"
          :icon="(isRequest || wasRemoved) ? 'send' : 'add_circle'"
          :label="(isRequest || wasRemoved) ? 'Send request' : 'Join trip'"
          :loading="loading"
          @click="$emit('confirm')"
        />
      </footer>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { computed } from 'vue'
import ConflictNotice from 'src/components/ConflictNotice.vue'
import { formatRange } from 'src/utils/schedule'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  trip: { type: Object, default: null },
  loading: { type: Boolean, default: false },
})
defineEmits(['update:modelValue', 'confirm'])

const isRequest = computed(() => props.trip?.visibility === 'invite_only' || !!props.trip?.requires_approval)
const wasRemoved = computed(() => !!props.trip?.viewer_removed)
</script>

<style scoped>
.jt-card { width: 420px; max-width: 94vw; border-radius: 16px; overflow: hidden; }

.jt-head {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 14px 13px; border-bottom: 1px solid #f4eff7;
  background: linear-gradient(180deg, #f7f0fb, #fff);
}
.jt-thumb { flex-shrink: 0; background: #efe4f6; box-shadow: 0 2px 8px rgba(43, 27, 51, 0.12); }
.jt-head-text { flex: 1; min-width: 0; }
.jt-title {
  font-size: 16px; font-weight: 700; line-height: 1.2;
  background: linear-gradient(135deg, #4a148c, #7b1fa2);
  -webkit-background-clip: text; background-clip: text; color: transparent;
}
.jt-sub {
  font-size: 12.5px; color: #7a6a82; margin-top: 2px;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

.jt-body { padding: 16px 14px 4px; }
.jt-facts {
  display: flex; flex-wrap: wrap; gap: 8px;
  padding: 10px 12px; border-radius: 12px;
  background: #fcfafd; border: 1px solid #ece6f0;
}
.jt-fact {
  display: flex; align-items: center; gap: 6px;
  font-size: 12.5px; color: #6b5a75;
}
.jt-fact .q-icon { color: #7b1fa2; }

.jt-conflict { margin-top: 12px; }

.jt-note {
  display: flex; align-items: flex-start; gap: 6px;
  margin: 13px 0 0; padding: 9px 11px;
  border-radius: 10px; background: #f7f3fa; border: 1px solid #f0eaf4;
  font-size: 11.5px; line-height: 1.45; color: #8a7a93;
}
.jt-note .q-icon { margin-top: 1px; flex-shrink: 0; color: #9b8aa5; }

.jt-actions {
  display: flex; justify-content: flex-end; gap: 8px;
  padding: 12px 14px 14px; border-top: 1px solid #f4eff7; background: #fcfafd;
}
</style>
