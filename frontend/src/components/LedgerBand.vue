<script setup>
/*
 * The signature element: one figure, given the space it deserves. It mirrors
 * the net-pay band printed on the payslip PDF so the screen and the document
 * read as the same object.
 */
defineProps({
  eyebrow: { type: String, required: true },
  figure: { type: String, required: true },
  caption: { type: String, default: null },
  meta: { type: String, default: null },
  muted: { type: Boolean, default: false },
})
</script>

<template>
  <div class="band" :class="{ 'band-muted': muted }">
    <div>
      <p class="eyebrow eyebrow-light">{{ eyebrow }}</p>
      <p class="band-figure">{{ figure }}</p>
      <p v-if="caption" class="band-caption">{{ caption }}</p>
    </div>
    <div v-if="meta || $slots.meta" class="band-meta">
      <slot name="meta">{{ meta }}</slot>
    </div>
  </div>
</template>
