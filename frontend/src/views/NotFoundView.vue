<script setup>
import { useRouter } from 'vue-router'
const router = useRouter()
</script>

<template>
  <div class="panel">
    <div class="empty">
      <p class="eyebrow">404</p>
      <p class="empty-title" style="margin-top: var(--s3)">That page is not here</p>
      <p>The link may be out of date, or the record may have been removed.</p>
      <button class="btn" style="margin-top: var(--s4)" @click="router.push({ name: 'overview' })">
        Back to overview
      </button>
    </div>
  </div>
</template>
