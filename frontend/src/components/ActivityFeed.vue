<script setup>
import AppIcon from '@/components/AppIcon.vue'
import { dateTime } from '@/format'

/*
 * A recent-activity list. It renders whatever the dashboard hands it, already
 * shaped into { icon, tone, text, at }. The composing happens on the dashboard
 * from data it has already fetched — payslips issued, leave decided, attendance
 * submitted — so this stays a dumb, reusable list with no data of its own.
 */
defineProps({
  items: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})
</script>

<template>
  <section class="panel">
    <div class="panel-head"><h2>Recent activity</h2></div>
    <div class="panel-body">
      <div v-if="loading" class="stack-tight">
        <div class="skeleton" style="width: 80%"></div>
        <div class="skeleton" style="width: 65%"></div>
        <div class="skeleton" style="width: 72%"></div>
      </div>

      <ul v-else-if="items.length" class="feed">
        <li v-for="(item, i) in items" :key="i" class="feed-item">
          <span class="feed-chip" :class="`chip-${item.tone || 'neutral'}`">
            <AppIcon :name="item.icon" :size="15" />
          </span>
          <div class="feed-body">
            <p class="feed-text" v-html="item.text"></p>
            <p class="ref">{{ dateTime(item.at) }}</p>
          </div>
        </li>
      </ul>

      <p v-else class="ref">No recent activity yet.</p>
    </div>
  </section>
</template>

<style scoped>
.feed {
  list-style: none;
  margin: 0;
  padding: 0;
}

.feed-item {
  display: flex;
  gap: var(--s3);
  padding: var(--s3) 0;
  border-bottom: 1px solid var(--rule);
}

.feed-item:last-child {
  border-bottom: 0;
}

.feed-chip {
  flex: 0 0 30px;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  background: var(--sunk);
  color: var(--muted);
}

.chip-brand { background: var(--teal-wash); color: var(--teal); }
.chip-positive { background: var(--green-wash); color: var(--green); }
.chip-pending { background: var(--amber-wash); color: var(--amber); }
.chip-negative { background: var(--red-wash); color: var(--red); }

.feed-body {
  flex: 1;
  min-width: 0;
}

.feed-text {
  font-size: var(--step-0);
  line-height: 1.4;
}

.feed-text :deep(strong) {
  font-weight: 600;
}
</style>
