<script setup>
import { ref } from 'vue'

/*
 * The company logo, with the drawn ledger mark as a fallback.
 *
 * Drop a file at frontend/public/logo.png (or point VITE_LOGO_URL elsewhere)
 * and it appears here and on the sign-in screen. If the file is absent or
 * fails to load, the mark renders instead — so a fresh checkout never shows a
 * broken image.
 */
defineProps({
  size: { type: Number, default: 16 },
  light: { type: Boolean, default: false },
})

const src = import.meta.env.VITE_LOGO_URL || '/logo.png'
const failed = ref(false)
</script>

<template>
  <img
    v-if="!failed"
    :src="src"
    alt=""
    class="logo"
    :style="{ height: `${size * 1.4}px` }"
    @error="failed = true"
  />
  <span
    v-else
    class="mark"
    :class="{ 'mark-light': light }"
    :style="{ width: `${size}px`, height: `${size}px` }"
    aria-hidden="true"
  ></span>
</template>

<style scoped>
.logo {
  display: block;
  width: auto;
  max-width: 180px;
  object-fit: contain;
}

/* A short stack of rules — a ledger seen edge-on. */
.mark {
  display: block;
  border-top: 3px solid var(--teal);
  border-bottom: 3px solid var(--teal);
  position: relative;
}

.mark::after {
  content: '';
  position: absolute;
  left: 0;
  right: 40%;
  top: 50%;
  transform: translateY(-50%);
  border-top: 3px solid var(--teal-line);
}

.mark-light {
  border-top-color: #fff;
  border-bottom-color: #fff;
}

.mark-light::after {
  border-top-color: var(--teal-line);
}
</style>
