<script setup>
import { computed } from 'vue'
import AppIcon from '@/components/AppIcon.vue'

/*
 * Upcoming staff birthdays. Presentational — the dashboard fetches and passes
 * them in. "Today" and "Tomorrow" are called out; everything else shows its
 * date and how many days away it is.
 */
const props = defineProps({
  items: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

function when(inDays, date) {
  if (inDays === 0) return 'Today'
  if (inDays === 1) return 'Tomorrow'
  const d = new Date(date + 'T00:00:00')
  return d.toLocaleDateString('en-MY', { weekday: 'short', day: 'numeric', month: 'short' })
}

function initials(name) {
  return name
    .split(' ')
    .slice(0, 2)
    .map((p) => p[0])
    .join('')
    .toUpperCase()
}

const soon = computed(() => props.items.slice(0, 6))
</script>

<template>
  <section class="panel">
    <div class="panel-head">
      <h2>Birthdays</h2>
      <span class="ref">next 30 days</span>
    </div>
    <div class="panel-body">
      <div v-if="loading" class="stack-tight">
        <div class="skeleton" style="width: 70%"></div>
        <div class="skeleton" style="width: 55%"></div>
      </div>

      <ul v-else-if="soon.length" class="bdays">
        <li v-for="b in soon" :key="b.employee_number" class="bday" :class="{ today: b.in_days === 0 }">
          <span class="bavatar"><AppIcon v-if="b.in_days === 0" name="calendarCheck" :size="15" /><template v-else>{{ initials(b.name) }}</template></span>
          <div class="bbody">
            <p class="bname">{{ b.name }}</p>
            <p class="ref">{{ b.department ?? b.employee_number }}</p>
          </div>
          <span class="bwhen" :class="{ hot: b.in_days <= 1 }">{{ when(b.in_days, b.date) }}</span>
        </li>
      </ul>

      <p v-else class="ref">No birthdays in the next 30 days.</p>
    </div>
  </section>
</template>

<style scoped>
.bdays {
  list-style: none;
  margin: 0;
  padding: 0;
}

.bday {
  display: flex;
  align-items: center;
  gap: var(--s3);
  padding: var(--s3) 0;
  border-bottom: 1px solid var(--rule);
}

.bday:last-child {
  border-bottom: 0;
}

.bavatar {
  flex: 0 0 32px;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  background: var(--teal-wash);
  color: var(--teal);
  font-family: var(--mono);
  font-size: 0.7rem;
  font-weight: 600;
}

.bday.today .bavatar {
  background: var(--teal);
  color: #fff;
}

.bbody {
  flex: 1;
  min-width: 0;
}

.bname {
  font-weight: 500;
  font-size: var(--step-0);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.bwhen {
  font-family: var(--mono);
  font-size: var(--step--1);
  color: var(--muted);
  white-space: nowrap;
}

.bwhen.hot {
  color: var(--teal);
  font-weight: 600;
}
</style>
