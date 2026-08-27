<script setup>
defineProps({
  label: { type: String, required: true },
  amount: { type: String, required: true },
  total: { type: Boolean, default: false },
  note: { type: String, default: null },
})
</script>

<template>
  <div class="ledger-row" :class="{ 'is-total': total }">
    <span class="label">
      {{ label }}
      <span v-if="note" class="ref" style="margin-left: 0.4rem">{{ note }}</span>
    </span>
    <span class="leader" aria-hidden="true"></span>
    <span class="amount">{{ amount }}</span>
  </div>
</template>
