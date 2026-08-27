<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppShell from '@/components/AppShell.vue'

const route = useRoute()
// Sign-in owns the whole viewport; everything else sits inside the shell.
const bare = computed(() => route.meta.public === true)
</script>

<template>
  <RouterView v-if="bare" v-slot="{ Component }">
    <component :is="Component" />
  </RouterView>

  <AppShell v-else>
    <RouterView v-slot="{ Component, route: current }">
      <Transition name="rise" mode="out-in">
        <component :is="Component" :key="current.path" />
      </Transition>
    </RouterView>
  </AppShell>
</template>
