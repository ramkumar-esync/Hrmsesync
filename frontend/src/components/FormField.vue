<script setup>
defineProps({
  label: { type: String, required: true },
  hint: { type: String, default: null },
  error: { type: String, default: null },
  id: { type: String, required: true },
})
</script>

<template>
  <div class="field">
    <label class="field-label" :for="id">{{ label }}</label>
    <slot :id="id" :invalid="Boolean(error)" />
    <span v-if="error" class="error">{{ error }}</span>
    <span v-else-if="hint" class="hint">{{ hint }}</span>
  </div>
</template>
