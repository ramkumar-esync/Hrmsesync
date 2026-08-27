<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useAsync } from '@/composables/useAsync'
import { attendance as attendanceApi, leave as leaveApi } from '@/api/resources'
import { readError } from '@/api/client'
import AppIcon from '@/components/AppIcon.vue'
import StatusTag from '@/components/StatusTag.vue'
import StateBlock from '@/components/StateBlock.vue'

/*
 * The current-and-recent months, newest first. Attendance is reported monthly,
 * so the picker only needs a short window rather than a free date field.
 */
const now = new Date()
const periods = Array.from({ length: 6 }, (_, i) => {
  const d = new Date(now.getFullYear(), now.getMonth() - i, 1)
  return {
    value: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`,
    label: d.toLocaleDateString('en-MY', { month: 'long', year: 'numeric' }),
  }
})
const period = ref(periods[0].value)

const state = useAsync((p) => attendanceApi.mine(p))
const types = useAsync(() => leaveApi.types(), [])

// The working copy of the rows, editable in the table.
const rows = ref([])
const saving = ref(false)
const submitting = ref(false)

/*
 * A left-to-right strip of the current week sits above the full month, so the
 * days that matter right now are the first thing seen. It defaults to the week
 * containing today, and can be stepped through without changing the month grid
 * below. Weeks run Monday→Sunday.
 */
const weekOffset = ref(0)

function mondayOf(date) {
  const d = new Date(date)
  const day = (d.getDay() + 6) % 7 // 0 = Monday
  d.setDate(d.getDate() - day)
  d.setHours(0, 0, 0, 0)
  return d
}

const weekDays = computed(() => {
  // Anchor to today, or to the first of the selected month if that month isn't
  // the current one, so stepping stays within the month being edited.
  const [y, m] = period.value.split('-').map(Number)
  const isThisMonth = y === now.getFullYear() && m === now.getMonth() + 1
  const anchor = isThisMonth ? new Date() : new Date(y, m - 1, 1)

  const start = mondayOf(anchor)
  start.setDate(start.getDate() + weekOffset.value * 7)

  return Array.from({ length: 7 }, (_, i) => {
    const d = new Date(start)
    d.setDate(start.getDate() + i)
    const iso = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
    return {
      iso,
      dayShort: d.toLocaleDateString('en-MY', { weekday: 'short' }),
      dayNum: d.getDate(),
      inMonth: d.getMonth() === m - 1,
      isToday: iso === `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`,
      weekend: d.getDay() === 0 || d.getDay() === 6,
    }
  })
})

const weekLabel = computed(() => {
  const days = weekDays.value
  const first = new Date(days[0].iso + 'T00:00:00')
  const last = new Date(days[6].iso + 'T00:00:00')
  const fmt = (d) => d.toLocaleDateString('en-MY', { day: 'numeric', month: 'short' })
  return `${fmt(first)} – ${fmt(last)}`
})

// The row for a given date, if the employee has one on the sheet.
function rowFor(iso) {
  return rows.value.find((r) => r.date === iso) ?? null
}

// Clicking a week-day card jumps to that row, adding it if missing.
function focusDay(iso, inMonth) {
  if (!inMonth || !editable.value) return
  let row = rowFor(iso)
  if (!row) {
    row = { date: iso, day: '', hours: '', leave_type_code: null, remarks: '' }
    rows.value.push(row)
    rows.value.sort((a, b) => a.date.localeCompare(b.date))
  }
  // Let the row render, then scroll to and highlight it.
  requestAnimationFrame(() => {
    const el = document.querySelector(`[data-date="${iso}"]`)
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'center' })
      el.classList.add('flash')
      setTimeout(() => el.classList.remove('flash'), 1200)
    }
  })
}
const error = ref(null)
const notice = ref(null)

const editable = computed(() => state.data.value?.editable ?? true)
const status = computed(() => state.data.value?.status ?? 'draft')
const decisionNote = computed(() => state.data.value?.decision_note ?? null)

const totalHours = computed(() =>
  rows.value.reduce((sum, r) => sum + (Number(r.hours) || 0), 0).toFixed(2),
)

/*
 * Every calendar day of the selected month, as blank rows. This is the default
 * the employee starts from — the whole month laid out — rather than an empty
 * sheet they have to build up. They then remove the days they don't want and
 * fill hours or a leave type on the ones they keep.
 */
function everyDayOfMonth() {
  const [y, m] = period.value.split('-').map(Number)
  const daysInMonth = new Date(y, m, 0).getDate()
  const out = []
  for (let d = 1; d <= daysInMonth; d++) {
    const date = `${period.value}-${String(d).padStart(2, '0')}`
    out.push({ date, day: '', hours: '', leave_type_code: null, remarks: '' })
  }
  return out
}

function load() {
  error.value = null
  notice.value = null
  state
    .run(period.value)
    .then(() => {
      const saved = state.data.value?.entries ?? []
      // A sheet already has rows → show those. A fresh, still-editable month →
      // pre-fill all its days so the employee starts from the full month.
      if (saved.length) {
        rows.value = saved.map((e) => ({ ...e }))
      } else if (state.data.value?.editable ?? true) {
        rows.value = everyDayOfMonth()
      } else {
        rows.value = []
      }
    })
    .catch(() => {})
}

onMounted(() => {
  types.run().catch(() => {})
  load()
})
watch(period, load)

// Add back any month days not currently in the table (after trimming, or to
// top up a partially-filled saved sheet).
function fillRemainingDays() {
  const have = new Set(rows.value.map((r) => r.date))
  const additions = everyDayOfMonth().filter((r) => !have.has(r.date))
  rows.value = [...rows.value, ...additions].sort((a, b) => a.date.localeCompare(b.date))
}

// Add a single blank row on the first unused day of the month.
function addRow() {
  const [y, m] = period.value.split('-').map(Number)
  const used = new Set(rows.value.map((r) => r.date))
  const daysInMonth = new Date(y, m, 0).getDate()

  let date = `${period.value}-01`
  for (let d = 1; d <= daysInMonth; d++) {
    const candidate = `${period.value}-${String(d).padStart(2, '0')}`
    if (!used.has(candidate)) {
      date = candidate
      break
    }
  }

  rows.value.push({ date, day: '', hours: '', leave_type_code: null, remarks: '' })
}

function removeRow(index) {
  rows.value.splice(index, 1)
}

// The weekday label for a row's date, shown read-only in the Day column.
function dayName(date) {
  if (!date) return '—'
  return new Date(date + 'T00:00:00').toLocaleDateString('en-MY', { weekday: 'long' })
}

function isWeekend(date) {
  if (!date) return false
  const d = new Date(date + 'T00:00:00').getDay()
  return d === 0 || d === 6
}

function cleanedEntries() {
  return rows.value
    .filter((r) => r.date)
    .map((r) => ({
      date: r.date,
      hours: r.hours === '' || r.hours === null ? 0 : Number(r.hours),
      leave_type_code: r.leave_type_code || null,
      remarks: r.remarks || null,
    }))
}

async function save() {
  error.value = null
  notice.value = null
  saving.value = true
  try {
    await attendanceApi.save({ period: period.value, entries: cleanedEntries() })
    notice.value = 'Saved.'
    load()
  } catch (caught) {
    error.value = readError(caught)
  } finally {
    saving.value = false
  }
}

async function submit() {
  error.value = null
  notice.value = null
  submitting.value = true
  try {
    // Save first so what HR sees is exactly what is on screen.
    await attendanceApi.save({ period: period.value, entries: cleanedEntries() })
    await attendanceApi.submit(period.value)
    notice.value = 'Submitted to HR.'
    load()
  } catch (caught) {
    error.value = readError(caught)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="stack">
    <header class="row-between">
      <div>
        <p class="eyebrow">Attendance</p>
        <h1 class="page-title">My attendance</h1>
        <p class="lede">
          The month starts with every day laid out. Fill in the hours or a leave
          type for the days that apply and remove the rest, then submit to HR. A
          row marked with a leave type must match leave you have already had
          approved for that day.
        </p>
      </div>
      <label class="field" style="margin: 0">
        <span class="field-label">Month</span>
        <select v-model="period" class="control control-mono">
          <option v-for="p in periods" :key="p.value" :value="p.value">{{ p.label }}</option>
        </select>
      </label>
    </header>

    <div v-if="notice" class="notice">{{ notice }}</div>
    <div v-if="error" class="notice notice-error">{{ error }}</div>

    <div v-if="status === 'returned' && decisionNote" class="notice notice-warn">
      <strong>HR returned this sheet for changes:</strong>
      <p style="margin-top: var(--s2)">{{ decisionNote }}</p>
    </div>

    <StateBlock :loading="state.loading.value" :error="state.error.value" :rows="4" @retry="load">
      <section class="panel">
        <div class="panel-head">
          <h2>{{ state.data.value?.period_label ?? 'This month' }}</h2>
          <div style="display: flex; align-items: center; gap: var(--s3)">
            <StatusTag :status="status" :label="state.data.value?.status_label" />
            <span class="ref">{{ totalHours }} h total</span>
          </div>
        </div>

        <div v-if="!editable" class="panel-body">
          <div class="notice" style="margin-bottom: var(--s4)">
            This sheet is {{ state.data.value?.status_label?.toLowerCase() }} and can no longer be
            edited.
          </div>
        </div>

        <!-- Current week, left to right. Defaults to the week with today in it. -->
        <div class="weekbar">
          <div class="weekbar-head">
            <span class="eyebrow">This week</span>
            <div class="weeknav">
              <button class="wk-btn" aria-label="Previous week" @click="weekOffset--">
                <AppIcon name="arrowLeft" :size="16" />
              </button>
              <span class="ref">{{ weekLabel }}</span>
              <button class="wk-btn" aria-label="Next week" @click="weekOffset++">
                <AppIcon name="chevron" :size="16" />
              </button>
              <button v-if="weekOffset !== 0" class="btn btn-quiet btn-small" @click="weekOffset = 0">
                Today
              </button>
            </div>
          </div>
          <div class="weekstrip">
            <button
              v-for="d in weekDays"
              :key="d.iso"
              class="daycard"
              :class="{
                out: !d.inMonth,
                today: d.isToday,
                weekend: d.weekend,
                filled: rowFor(d.iso),
              }"
              :disabled="!d.inMonth || !editable"
              @click="focusDay(d.iso, d.inMonth)"
            >
              <span class="dc-day">{{ d.dayShort }}</span>
              <span class="dc-num figure">{{ d.dayNum }}</span>
              <span v-if="rowFor(d.iso)" class="dc-val">
                <template v-if="rowFor(d.iso).leave_type_code">
                  {{ rowFor(d.iso).leave_type_code }}
                </template>
                <template v-else-if="rowFor(d.iso).hours">
                  {{ rowFor(d.iso).hours }}h
                </template>
                <template v-else>·</template>
              </span>
              <span v-else-if="d.inMonth && editable" class="dc-add">+</span>
            </button>
          </div>
        </div>

        <div class="table-wrap">
          <table class="data">
            <thead>
              <tr>
                <th style="width: 9.5rem">Date</th>
                <th style="width: 8rem">Day</th>
                <th class="num" style="width: 6rem">Hours</th>
                <th style="width: 12rem">Leave type</th>
                <th>Remarks</th>
                <th v-if="editable"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, index) in rows" :key="index" :data-date="row.date" :class="{ weekend: isWeekend(row.date) }">
                <td>
                  <input
                    v-model="row.date"
                    type="date"
                    class="control control-mono"
                    :min="`${period}-01`"
                    :disabled="!editable"
                  />
                </td>
                <td class="ref">{{ dayName(row.date) }}</td>
                <td class="num">
                  <input
                    v-model="row.hours"
                    type="number"
                    step="0.5"
                    min="0"
                    max="24"
                    class="control control-mono"
                    :disabled="!editable"
                    style="text-align: right"
                  />
                </td>
                <td>
                  <select v-model="row.leave_type_code" class="control" :disabled="!editable">
                    <option :value="null">— Worked —</option>
                    <option v-for="t in types.data.value" :key="t.code" :value="t.code">
                      {{ t.name }}
                    </option>
                  </select>
                </td>
                <td>
                  <input
                    v-model="row.remarks"
                    type="text"
                    class="control"
                    maxlength="500"
                    :disabled="!editable"
                    placeholder="Optional"
                  />
                </td>
                <td v-if="editable" style="text-align: right">
                  <button class="icon-btn" aria-label="Remove row" @click="removeRow(index)">
                    <AppIcon name="close" :size="16" />
                  </button>
                </td>
              </tr>
              <tr v-if="!rows.length">
                <td :colspan="editable ? 6 : 5" class="ref" style="text-align: center; padding: var(--s6)">
                  No rows yet. Add the days you want to report.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="editable" class="sheet-actions">
          <button class="btn btn-quiet btn-small" @click="addRow">
            <AppIcon name="plus" :size="16" /> Add row
          </button>
          <button
            v-if="rows.length < 28"
            class="btn btn-quiet btn-small"
            @click="fillRemainingDays"
          >
            Fill all days
          </button>
          <div style="flex: 1"></div>
          <button class="btn btn-quiet" :disabled="saving || submitting" @click="save">
            {{ saving ? 'Saving…' : 'Save draft' }}
          </button>
          <button class="btn" :disabled="saving || submitting || !rows.length" @click="submit">
            {{ submitting ? 'Submitting…' : 'Submit to HR' }}
          </button>
        </div>
      </section>
    </StateBlock>
  </div>
</template>

<style scoped>
.sheet-actions {
  display: flex;
  align-items: center;
  gap: var(--s3);
  padding: var(--s4) var(--s5);
  border-top: 1px solid var(--rule);
  background: var(--sunk);
}

.icon-btn {
  background: none;
  border: 1px solid transparent;
  border-radius: var(--radius-sm);
  padding: 0.3rem;
  cursor: pointer;
  color: var(--muted);
  display: inline-flex;
}

.icon-btn:hover {
  background: var(--red-wash);
  color: var(--red);
}

/* Inline table inputs sit flush and quiet until focused. */
.data .control {
  padding: 0.35rem 0.5rem;
}

/* Weekends tinted so a full month reads at a glance. */
.data tbody tr.weekend td {
  background: var(--sunk);
}

.data tbody tr.weekend:hover td {
  background: var(--teal-tint);
}

/* Brief highlight when a week-card jumps to its row. */
.data tbody tr.flash td {
  animation: flash 1.2s ease;
}

@keyframes flash {
  0%, 40% { background: var(--amber-wash); }
  100% { background: transparent; }
}

/* ---------------------------------------------------------- Week strip */
.weekbar {
  padding: var(--s4) var(--s5);
  border-bottom: 1px solid var(--rule);
  background: var(--surface);
}

.weekbar-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--s3);
}

.weeknav {
  display: flex;
  align-items: center;
  gap: var(--s2);
}

.wk-btn {
  display: inline-flex;
  padding: 0.25rem;
  border: 1px solid var(--rule-strong);
  border-radius: var(--radius-sm);
  background: var(--surface);
  color: var(--ink-soft);
  cursor: pointer;
}

.wk-btn:hover {
  background: var(--sunk);
}

.weekstrip {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: var(--s2);
}

.daycard {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.15rem;
  padding: var(--s3) var(--s2);
  border: 1px solid var(--rule);
  border-radius: var(--radius-sm);
  background: var(--surface);
  cursor: pointer;
  transition: border-color 120ms ease, background 120ms ease, transform 80ms ease;
  min-height: 78px;
  justify-content: center;
}

.daycard:hover:not(:disabled) {
  border-color: var(--teal);
  transform: translateY(-1px);
}

.daycard:disabled {
  cursor: default;
}

.daycard.out {
  opacity: 0.4;
}

.daycard.weekend {
  background: var(--sunk);
}

.daycard.today {
  border-color: var(--teal);
  box-shadow: inset 0 0 0 1px var(--teal);
}

.daycard.filled {
  background: var(--teal-tint);
  border-color: var(--teal-line);
}

.dc-day {
  font-family: var(--mono);
  font-size: 0.625rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--muted);
}

.dc-num {
  font-size: var(--step-1);
  font-weight: 600;
  line-height: 1;
}

.dc-val {
  font-family: var(--mono);
  font-size: 0.6875rem;
  color: var(--teal);
  font-weight: 600;
}

.dc-add {
  font-size: var(--step-1);
  color: var(--faint);
  line-height: 1;
}

@media (max-width: 640px) {
  .weekstrip {
    gap: 3px;
  }
  .daycard {
    min-height: 64px;
    padding: var(--s2) 2px;
  }
}
</style>
