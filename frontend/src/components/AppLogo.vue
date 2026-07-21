<template>
  <div class="brand" :class="{ 'brand--light': light }">
    <!-- Placeholder mark: peaks + a route line. Swap for the real logo later. -->
    <svg
      class="brand-mark"
      :width="size"
      :height="size"
      viewBox="0 0 40 40"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
    >
      <defs>
        <linearGradient :id="gradId" x1="0" y1="0" x2="40" y2="40" gradientUnits="userSpaceOnUse">
          <stop stop-color="#7B1FA2" />
          <stop offset="1" stop-color="#3D1152" />
        </linearGradient>
      </defs>

      <rect width="40" height="40" rx="11" :fill="`url(#${gradId})`" />

      <!-- sun -->
      <circle cx="28" cy="12" r="4" fill="#FFD37A" />

      <!-- back peak -->
      <path d="M4 30 L14 15.5 L21 24 L17 30 Z" fill="#FFFFFF" opacity="0.42" />
      <!-- front peak: the shape that has to read at 28px -->
      <path d="M11 30 L21.5 14 L32 30 Z" fill="#FFFFFF" />
      <!-- snow notch -->
      <path d="M18.4 19.5 L21.5 14 L24.6 19.5 L21.5 17.8 Z" fill="#E9D7F2" />

      <!-- ground -->
      <rect x="4" y="30" width="28" height="2.6" rx="1.3" fill="#FFD37A" />
    </svg>

    <span v-if="wordmark" class="brand-word" :style="{ fontSize: `${wordSize}px` }">
      <span class="brand-word-1">Mera</span><span class="brand-word-2">Musafir</span>
    </span>
  </div>
</template>

<script setup>
import { computed } from 'vue'

defineProps({
  size: { type: Number, default: 32 },
  wordSize: { type: Number, default: 19 },
  wordmark: { type: Boolean, default: true },
  light: { type: Boolean, default: false },
})

// Unique gradient id so multiple logos on one page don't collide.
const gradId = computed(() => `brand-grad-${Math.random().toString(36).slice(2, 8)}`)
</script>

<style scoped>
.brand { display: inline-flex; align-items: center; gap: 10px; line-height: 1; }
.brand-mark { flex-shrink: 0; border-radius: 11px; }

.brand-word {
  font-family: 'Poppins', system-ui, sans-serif;
  letter-spacing: -0.015em;
  white-space: nowrap;
}
.brand-word-1 { font-weight: 300; opacity: 0.85; }
.brand-word-2 { font-weight: 700; margin-left: 0.28em; }

/* On the purple app bar */
.brand--light .brand-word { color: #fff; }
.brand--light .brand-word-1 { opacity: 0.78; }
</style>
