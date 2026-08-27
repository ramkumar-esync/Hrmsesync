<script setup>
/*
 * Loading, failure and emptiness handled in one place.
 *
 * An empty screen is an invitation to act, and an error says what went wrong
 * rather than apologising — so both take a title and an optional action.
 */
defineProps({
  loading: { type: Boolean, default: false },
  error: { type: String, default: null },
  empty: { type: Boolean, default: false },
  emptyTitle: { type: String, default: 'Nothing here yet' },
  emptyBody: { type: String, default: null },
  rows: { type: Number, default: 3 },
})

const emit = defineEmits(['retry'])
</script>

<template>
  <div v-if="loading" class="empty" style="padding: var(--s5)">
    <div v-for="n in rows" :key="n" class="skeleton" :style="{ marginBottom: '0.9rem', width: `${100 - n * 8}%` }"></div>
  </div>

  <div v-else-if="error" class="notice notice-error" style="margin: var(--s5)">
    <p style="font-weight: 600; margin-bottom: 0.35rem">That request did not go through</p>
    <p>{{ error }}</p>
    <button class="btn btn-quiet btn-small" style="margin-top: 0.7rem" @click="emit('retry')">
      Try again
    </button>
  </div>

  <div v-else-if="empty" class="empty">
    <p class="empty-title">{{ emptyTitle }}</p>
    <p v-if="emptyBody">{{ emptyBody }}</p>
    <slot name="action" />
  </div>

  <slot v-else />
</template>
