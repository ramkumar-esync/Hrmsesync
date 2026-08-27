<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAsync } from '@/composables/useAsync'
import { attendance as attendanceApi } from '@/api/resources'
import { readError } from '@/api/client'
import { dateTime, date } from '@/format'
import AppIcon from '@/components/AppIcon.vue'
import StateBlock from '@/components/StateBlock.vue'

const queue = useAsync(() => attendanceApi.pending(), [])
const selected = ref(null)
const detail = ref(null)
const loadingDetail = ref(false)
const busy = ref(false)
const error = ref(null)
const notice = ref(null)
const returning = ref(false)
const note = ref('')

onMounted(() => queue.run().catch(() => {}))

const rows = computed(() => queue.data.value ?? [])

async function open(sheet) {
  selected.value = sheet.id
  detail.value = null
  returning.value = false
  note.value = ''
  error.value = null
  loadingDetail.value = true
  try {
    detail.value = await attendanceApi.show(sheet.id)
  } catch (caught) {
    error.value = readError(caught)
  } finally {
    loadingDetail.value = false
  }
}

async function decide(approve) {
  if (!approve && !returning.value) {
    returning.value = true
    return
  }
  if (!approve && note.value.trim().length < 3) {
    error.value = 'Say what needs changing so the employee can fix it.'
    return
  }

  error.value = null
  busy.value = true
  try {
    await attendanceApi.decide(selected.value, {
      decision: approve ? 'approve' : 'return',
      note: approve ? undefined : note.value,
    })
    notice.value = approve
      ? 'Sheet approved.'
      : 'Sheet returned to the employee for changes.'
    selected.value = null
    detail.value = null
    await queue.run()
  } catch (caught) {
    error.value = readError(caught)
  } finally {
    busy.value = false
  }
}

const sheet = computed(() => detail.value?.data ?? null)
const meta = computed(() => detail.value?.meta ?? {})
</script>

<template>
  <div class="stack">
    <header>
      <p class="eyebrow">Attendance</p>
      <h1 class="page-title">Attendance to review</h1>
      <p class="lede">
        Sheets employees have submitted for the month. Approving accepts the
        record; returning sends it back with your note for another pass.
      </p>
    </header>

    <div v-if="notice" class="notice">{{ notice }}</div>
    <div v-if="error" class="notice notice-error">{{ error }}</div>

    <div class="review-grid">
      <section class="panel">
        <div class="panel-head"><h2>Queue</h2><span class="ref">{{ rows.length }} waiting</span></div>
        <StateBlock
          :loading="queue.loading.value"
          :error="queue.error.value"
          :empty="rows.length === 0"
          empty-title="Nothing to review"
          empty-body="Submitted attendance sheets appear here."
          @retry="queue.run()"
        >
          <ul class="queue">
            <li v-for="row in rows" :key="row.id">
              <button class="queue-item" :class="{ active: selected === row.id }" @click="open(row)">
                <span class="q-name">{{ row.employee_name }}</span>
                <span class="ref">{{ row.employee_number }} · {{ row.period }}</span>
                <span class="q-meta">
                  <span class="figure">{{ row.total_hours }}</span> h ·
                  submitted {{ dateTime(row.submitted_at) }}
                </span>
              </button>
            </li>
          </ul>
        </StateBlock>
      </section>

      <section class="panel">
        <div v-if="loadingDetail" class="panel-body">
          <div class="skeleton" style="width: 60%; margin-bottom: 1rem"></div>
          <div class="skeleton" style="width: 90%; margin-bottom: 0.6rem"></div>
          <div class="skeleton" style="width: 80%"></div>
        </div>

        <div v-else-if="!sheet" class="empty">
          <p class="empty-title">Pick a sheet</p>
          <p>Choose someone from the queue to see their month.</p>
        </div>

        <template v-else>
          <div class="panel-head">
            <div>
              <h2>{{ meta.employee_name }}</h2>
              <p class="ref">{{ meta.employee_number }} · {{ sheet.period_label }}</p>
            </div>
            <span class="ref">{{ sheet.total_hours }} h total</span>
          </div>

          <div class="table-wrap">
            <table class="data">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Day</th>
                  <th class="num">Hours</th>
                  <th>Leave type</th>
                  <th>Remarks</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(entry, i) in sheet.entries" :key="i">
                  <td class="ref">{{ date(entry.date) }}</td>
                  <td>{{ entry.day }}</td>
                  <td class="num">{{ entry.hours }}</td>
                  <td>
                    <span v-if="entry.leave_type_code" class="tag tag-brand">
                      {{ entry.leave_type_code }}
                    </span>
                    <span v-else class="ref">Worked</span>
                  </td>
                  <td>{{ entry.remarks ?? '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="decide">
            <div v-if="returning" class="field" style="margin-bottom: var(--s3)">
              <label class="field-label" for="note">What needs changing?</label>
              <textarea
                id="note"
                v-model="note"
                class="control"
                rows="2"
                placeholder="The employee sees this."
              ></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: var(--s3)">
              <button class="btn btn-quiet" :disabled="busy" @click="decide(false)">
                {{ returning ? 'Confirm return' : 'Return for changes' }}
              </button>
              <button class="btn" :disabled="busy" @click="decide(true)">
                {{ busy ? 'Saving…' : 'Approve' }}
              </button>
            </div>
          </div>
        </template>
      </section>
    </div>
  </div>
</template>

<style scoped>
.review-grid {
  display: grid;
  grid-template-columns: minmax(280px, 340px) 1fr;
  gap: var(--s5);
  align-items: start;
}

.queue {
  list-style: none;
  margin: 0;
  padding: 0;
}

.queue-item {
  width: 100%;
  text-align: left;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  padding: var(--s4) var(--s5);
  background: none;
  border: none;
  border-bottom: 1px solid var(--rule);
  cursor: pointer;
  transition: background 120ms ease;
}

.queue-item:hover {
  background: var(--sunk);
}

.queue-item.active {
  background: var(--teal-wash);
  box-shadow: inset 3px 0 0 var(--teal);
}

.q-name {
  font-weight: 600;
}

.q-meta {
  font-size: var(--step--1);
  color: var(--muted);
  margin-top: 0.1rem;
}

.decide {
  padding: var(--s5);
  border-top: 1px solid var(--rule);
  background: var(--sunk);
}

@media (max-width: 860px) {
  .review-grid {
    grid-template-columns: 1fr;
  }
}
</style>
