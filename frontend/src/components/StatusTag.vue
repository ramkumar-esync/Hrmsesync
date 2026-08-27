<script setup>
import { computed } from 'vue'
import { statusTone, statusLabel } from '@/format'

const props = defineProps({
  status: { type: String, required: true },
  label: { type: String, default: null },
})

const tone = computed(() => statusTone(props.status))
const text = computed(() => props.label ?? statusLabel(props.status))
</script>

<template>
  <span class="tag" :class="`tag-${tone}`">{{ text }}</span>
</template>
