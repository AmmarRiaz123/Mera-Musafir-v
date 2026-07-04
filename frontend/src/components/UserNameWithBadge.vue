<template>
  <span class="name-badge">
    {{ name }}
    <q-icon v-if="isVerified" name="verified" color="deep-purple" size="14px" class="q-ml-xs">
      <q-tooltip>Verified User</q-tooltip>
    </q-icon>
  </span>
</template>

<script setup>
defineProps({
  name:       { type: String, default: '' },
  isVerified: { type: Boolean, default: false },
})
</script>

<style scoped>
.name-badge {
  display: inline-flex;
  align-items: center;
  gap: 2px;
}
</style>
