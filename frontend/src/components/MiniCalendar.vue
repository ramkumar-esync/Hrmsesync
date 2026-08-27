<script setup>
import { ref, computed } from 'vue'
import AppIcon from '@/components/AppIcon.vue'

/*
 * A compact month calendar. It doesn't own any data — the parent passes a list
 * of marked date ranges (e.g. the person's approved leave), and the grid tints
 * the days they cover. Kept presentational so the same calendar can show leave
 * on the employee dashboard or, later, anything else with dates.
 */
const props = defineProps({
  // [{ from: 'YYYY-MM-DD', to: 'YYYY-MM-DD', tone: 'brand'|'pending'|'holiday'|'task', label: '' }]
  ranges: { type: Array, default: () => [] },
  title: { type: String, default: 'Calendar' },
  // When true, clicking a day opens a detail popover with an add-task box.
  interactive: { type: Boolean, default: false },
})

const emit = defineEmits(['add-task'])

const cursor = ref(new Date())
const selected = ref(null) // the iso date whose popover is open
const taskDraft = ref('')

const monthLabel = computed(() =>
  cursor.value.toLocaleDateString('en-MY', { month: 'long', year: 'numeric' }),
)

function iso(d) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(
    d.getDate(),
  ).padStart(2, '0')}`
}

// Mon–Sun grid as one flat list of 42 cells (6 rows), so every day sits in one
// continuous grid and the columns can't drift row to row. Leading and trailing
// slots are null and render as blanks.
const cells = computed(() => {
  const y = cursor.value.getFullYear()
  const m = cursor.value.getMonth()
  const first = new Date(y, m, 1)
  const lead = (first.getDay() + 6) % 7 // Monday-first
  const daysInMonth = new Date(y, m + 1, 0).getDate()

  const out = []
  for (let i = 0; i < lead; i++) out.push(null)
  for (let d = 1; d <= daysInMonth; d++) out.push(new Date(y, m, d))
  while (out.length % 7 !== 0) out.push(null)
  return out
})

const todayIso = iso(new Date())

function markFor(date) {
  if (!date) return null
  const key = iso(date)
  for (const r of props.ranges) {
    if (key >= r.from && key <= r.to) return r
  }
  return null
}

// Every range covering a given iso date (a day can have leave + holiday + task).
function allFor(key) {
  return props.ranges.filter((r) => key >= r.from && key <= r.to)
}

const selectedItems = computed(() => (selected.value ? allFor(selected.value) : []))

const selectedLabel = computed(() => {
  if (!selected.value) return ''
  return new Date(selected.value + 'T00:00:00').toLocaleDateString('en-MY', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  })
})

function toneLabel(tone) {
  return (
    { brand: 'Leave', pending: 'Leave (pending)', holiday: 'Holiday', task: 'Task' }[tone] ||
    'Note'
  )
}

function pickDay(date) {
  if (!props.interactive || !date) return
  const key = iso(date)
  selected.value = selected.value === key ? null : key
  taskDraft.value = ''
}

function addTask() {
  const text = taskDraft.value.trim()
  if (!text || !selected.value) return
  // Default the time to 9:00 AM on the chosen day.
  emit('add-task', { text, due: `${selected.value}T09:00` })
  taskDraft.value = ''
}

function step(delta) {
  cursor.value = new Date(cursor.value.getFullYear(), cursor.value.getMonth() + delta, 1)
  selected.value = null
}
</script>

<template>
  <section class="panel">
    <div class="panel-head">
      <h2>{{ title }}</h2>
      <div class="cal-nav">
        <button class="cal-btn" aria-label="Previous month" @click="step(-1)">
          <AppIcon name="arrowLeft" :size="15" />
        </button>
        <span class="ref cal-month">{{ monthLabel }}</span>
        <button class="cal-btn" aria-label="Next month" @click="step(1)">
          <AppIcon name="chevron" :size="15" />
        </button>
      </div>
    </div>
    <div class="panel-body">
      <div class="cal-grid cal-head">
        <span v-for="d in ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su']" :key="d">{{ d }}</span>
      </div>
      <div class="cal-grid cal-body">
        <template v-for="(date, i) in cells" :key="i">
          <span v-if="!date" class="cal-cell empty" aria-hidden="true"></span>
          <button
            v-else
            type="button"
            class="cal-cell"
            :class="[
              markFor(date) ? `mark-${markFor(date).tone}` : '',
              iso(date) === todayIso ? 'is-today' : '',
              interactive ? 'clickable' : '',
              selected === iso(date) ? 'picked' : '',
            ]"
            :title="markFor(date)?.label"
            @click="pickDay(date)"
          >
            {{ date.getDate() }}
          </button>
        </template>
      </div>

      <!-- Day detail: what's on the chosen day, plus a quick add-task box. -->
      <div v-if="interactive && selected" class="cal-day">
        <div class="cal-day-head">
          <span class="cal-day-title">{{ selectedLabel }}</span>
          <button class="cal-day-close" aria-label="Close" @click="selected = null">
            <AppIcon name="close" :size="15" />
          </button>
        </div>

        <ul v-if="selectedItems.length" class="cal-day-list">
          <li v-for="(item, k) in selectedItems" :key="k" class="cal-day-item">
            <i class="dot" :class="`mark-${item.tone}`"></i>
            <span class="cal-day-kind">{{ toneLabel(item.tone) }}</span>
            <span class="cal-day-label">{{ item.label }}</span>
          </li>
        </ul>
        <p v-else class="ref cal-day-empty">Nothing scheduled.</p>

        <div class="cal-day-add">
          <input
            v-model="taskDraft"
            class="control"
            type="text"
            placeholder="Add a task on this day"
            maxlength="200"
            @keyup.enter="addTask"
          />
          <button class="btn btn-small" :disabled="!taskDraft.trim()" @click="addTask">Add</button>
        </div>
      </div>

      <div v-if="ranges.length" class="cal-legend">
        <span class="lg"><i class="dot mark-brand"></i> Approved leave</span>
        <span class="lg"><i class="dot mark-pending"></i> Pending</span>
        <span class="lg"><i class="dot mark-holiday"></i> Holiday</span>
        <span class="lg"><i class="dot mark-task"></i> Task</span>
      </div>
    </div>
  </section>
</template>

<style scoped>
.cal-nav {
  display: flex;
  align-items: center;
  gap: var(--s2);
}

.cal-month {
  min-width: 8.5rem;
  text-align: center;
}

.cal-btn {
  display: inline-flex;
  padding: 0.2rem;
  border: 1px solid var(--rule-strong);
  border-radius: var(--radius-sm);
  background: var(--surface);
  color: var(--ink-soft);
  cursor: pointer;
}

.cal-btn:hover {
  background: var(--sunk);
}

.cal-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 3px;
}

.cal-body {
  grid-auto-rows: 34px;
}

.cal-head {
  margin-bottom: var(--s2);
}

.cal-head span {
  text-align: center;
  font-family: var(--mono);
  font-size: 0.625rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--muted);
}

.cal-cell {
  display: grid;
  place-items: center;
  font-family: var(--mono);
  font-variant-numeric: tabular-nums;
  font-size: var(--step--1);
  border-radius: var(--radius-sm);
  color: var(--ink-soft);
  border: none;
  background: transparent;
  font: inherit;
  font-family: var(--mono);
  padding: 0;
}

.cal-cell.clickable {
  cursor: pointer;
  transition: box-shadow 100ms ease, transform 80ms ease;
}

.cal-cell.clickable:hover {
  box-shadow: inset 0 0 0 1px var(--teal-line);
}

.cal-cell.picked {
  box-shadow: inset 0 0 0 2px var(--teal);
}

.cal-cell.empty {
  visibility: hidden;
}

.cal-cell.is-today {
  box-shadow: inset 0 0 0 1.5px var(--teal);
  font-weight: 600;
  color: var(--teal);
}

.mark-brand {
  background: var(--teal);
  color: #fff;
}

.mark-pending {
  background: var(--amber-wash);
  color: var(--amber);
}

/* Holidays get their own colour, distinct from leave (teal) and pending (amber). */
.mark-holiday {
  background: var(--red-wash, #fbe9e7);
  color: var(--red, #b3402f);
}

.dot.mark-holiday {
  background: var(--red, #b3402f);
}

/* To-do tasks with a due date — a distinct violet so they don't read as leave. */
.mark-task {
  background: #ede9fe;
  color: #6d28d9;
}

.dot.mark-task {
  background: #6d28d9;
}

.cal-legend {
  display: flex;
  gap: var(--s4);
  margin-top: var(--s4);
  padding-top: var(--s3);
  border-top: 1px solid var(--rule);
}

.lg {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-size: var(--step--1);
  color: var(--muted);
}

.dot {
  width: 10px;
  height: 10px;
  border-radius: 3px;
  display: inline-block;
}

/* ------------------------------------------------------- Day detail panel */
.cal-day {
  margin-top: var(--s4);
  padding-top: var(--s4);
  border-top: 1px solid var(--rule);
}

.cal-day-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--s3);
}

.cal-day-title {
  font-weight: 600;
  font-size: var(--step-0);
}

.cal-day-close {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--muted);
  display: inline-flex;
  padding: 0.15rem;
}

.cal-day-list {
  list-style: none;
  margin: 0 0 var(--s3);
  padding: 0;
}

.cal-day-item {
  display: flex;
  align-items: center;
  gap: var(--s2);
  padding: var(--s2) 0;
  font-size: var(--step--1);
}

.cal-day-kind {
  font-family: var(--mono);
  font-size: 0.625rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--muted);
  flex: 0 0 auto;
}

.cal-day-label {
  color: var(--ink-soft);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cal-day-empty {
  padding: var(--s2) 0 var(--s3);
}

.cal-day-add {
  display: flex;
  gap: var(--s2);
}
</style>