<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import { useRouter } from 'vue-router'
import { notifications as notifApi } from '@/api/resources'
import AppIcon from '@/components/AppIcon.vue'

/*
 * The notification bell in the app chrome. It fetches on mount (so a decision
 * made while the user was away shows the moment they open the app), shows an
 * unread count, and marks everything read when the panel is opened — which is
 * what stops the same notification resurfacing on the next login.
 */
const router = useRouter()

const items = ref([])
const unread = ref(0)
const open = ref(false)
const loading = ref(false)

async function load() {
  loading.value = true
  try {
    const res = await notifApi.list()
    items.value = res.data ?? []
    unread.value = res.meta?.unread ?? 0
  } catch {
    // Silent — the bell just shows nothing rather than erroring in the chrome.
  } finally {
    loading.value = false
  }
}

async function toggle() {
  open.value = !open.value
  if (open.value && unread.value > 0) {
    // Optimistically clear the badge, then persist.
    const had = unread.value
    unread.value = 0
    items.value = items.value.map((n) => ({ ...n, read: true }))
    try {
      await notifApi.markRead()
    } catch {
      unread.value = had // restore on failure
    }
  }
}

function act(item) {
  open.value = false
  if (item.action_url) router.push(item.action_url)
}

function timeAgo(iso) {
  if (!iso) return ''
  const s = Math.floor((Date.now() - new Date(iso).getTime()) / 1000)
  if (s < 60) return 'just now'
  if (s < 3600) return `${Math.floor(s / 60)}m ago`
  if (s < 86400) return `${Math.floor(s / 3600)}h ago`
  return `${Math.floor(s / 86400)}d ago`
}

function onDocClick(e) {
  if (!e.target.closest('.bell-wrap')) open.value = false
}

onMounted(() => {
  load()
  document.addEventListener('click', onDocClick)
})
onBeforeUnmount(() => document.removeEventListener('click', onDocClick))

const hasItems = computed(() => items.value.length > 0)
</script>

<template>
  <div class="bell-wrap">
    <button class="bell" :aria-label="`Notifications${unread ? `, ${unread} unread` : ''}`" @click.stop="toggle">
      <AppIcon name="clock" :size="18" />
      <span v-if="unread" class="badge">{{ unread > 9 ? '9+' : unread }}</span>
    </button>

    <div v-if="open" class="bell-panel">
      <div class="bell-head">Notifications</div>
      <div v-if="loading" class="bell-empty">Loading…</div>
      <ul v-else-if="hasItems" class="bell-list">
        <li
          v-for="n in items"
          :key="n.id"
          class="bell-item"
          :class="{ unread: !n.read, clickable: n.action_url }"
          @click="act(n)"
        >
          <p class="bell-title">{{ n.title }}</p>
          <p v-if="n.body" class="bell-body">{{ n.body }}</p>
          <p class="bell-time">{{ timeAgo(n.created_at) }}</p>
        </li>
      </ul>
      <div v-else class="bell-empty">You're all caught up.</div>
    </div>
  </div>
</template>

<style scoped>
.bell-wrap {
  position: relative;
}

.bell {
  position: relative;
  background: none;
  border: none;
  cursor: pointer;
  color: var(--sidebar-ink, #cdd6d3);
  display: inline-flex;
  padding: var(--s2);
  border-radius: var(--radius-sm);
}

.bell:hover {
  background: var(--sidebar-hover, rgba(255, 255, 255, 0.06));
}

.badge {
  position: absolute;
  top: 0;
  right: 0;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: 8px;
  background: var(--red, #b3402f);
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  display: grid;
  place-items: center;
  line-height: 1;
}

.bell-panel {
  position: absolute;
  top: calc(100% + 6px);
  width: 320px;
  max-width: 88vw;
  background: var(--surface);
  border: 1px solid var(--rule);
  border-radius: var(--radius);
  box-shadow: var(--shadow-lg);
  z-index: 60;
  overflow: hidden;
}

.bell-head {
  padding: var(--s3) var(--s4);
  font-weight: 600;
  font-size: var(--step-0);
  border-bottom: 1px solid var(--rule);
  color: var(--ink);
}

.bell-list {
  list-style: none;
  margin: 0;
  padding: 0;
  max-height: 380px;
  overflow-y: auto;
}

.bell-item {
  padding: var(--s3) var(--s4);
  border-bottom: 1px solid var(--rule);
}

.bell-item:last-child {
  border-bottom: 0;
}

.bell-item.clickable {
  cursor: pointer;
}

.bell-item.clickable:hover {
  background: var(--sunk);
}

.bell-item.unread {
  background: var(--teal-tint);
}

.bell-title {
  font-weight: 600;
  font-size: var(--step-0);
  color: var(--ink);
}

.bell-body {
  font-size: var(--step--1);
  color: var(--ink-soft);
  margin-top: 0.15rem;
  line-height: 1.4;
}

.bell-time {
  font-family: var(--mono);
  font-size: 0.6875rem;
  color: var(--muted);
  margin-top: 0.25rem;
}

.bell-empty {
  padding: var(--s5) var(--s4);
  text-align: center;
  color: var(--muted);
  font-size: var(--step--1);
}
</style>
