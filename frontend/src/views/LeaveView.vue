<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useAsync } from '@/composables/useAsync'
import { leave as leaveApi } from '@/api/resources'
import { readError } from '@/api/client'
import { days, dateRange, dateTime } from '@/format'
import StatusTag from '@/components/StatusTag.vue'
import StateBlock from '@/components/StateBlock.vue'

const thisYear = new Date().getFullYear()
const year = ref(thisYear)
const years = Array.from({ length: 4 }, (_, i) => thisYear - i)

const balances = useAsync((selected) => leaveApi.balances(selected))
const applications = useAsync((params) => leaveApi.applications(params))

const cancelling = ref(null)
const actionError = ref(null)

function load() {
  balances.run(year.value).catch(() => {})
  applications.run({ year: year.value }).catch(() => {})
}

onMounted(load)
watch(year, load)

const balanceRows = computed(() => balances.data.value?.data ?? [])
const applicationRows = computed(() => applications.data.value?.data ?? [])

async function cancel(application) {
  actionError.value = null
  cancelling.value = application.id
  try {
    await leaveApi.cancel(application.id)
    load()
  } catch (error) {
    actionError.value = readError(error)
  } finally {
    cancelling.value = null
  }
}

/** Pending and approved future leave can still be withdrawn by the employee. */
function canCancel(application) {
  if (!['pending', 'approved'].includes(application.status)) return false
  return new Date(application.start_date) > new Date()
}
</script>

<template>
  <div class="stack">
    <header class="row-between">
      <div>
        <p class="eyebrow">Leave</p>
        <h1 class="page-title">My leave</h1>
      </div>
      <div style="display: flex; gap: var(--s3); align-items: flex-end">
        <label class="field" style="margin: 0">
          <span class="field-label">Year</span>
          <select v-model.number="year" class="control control-mono">
            <option v-for="option in years" :key="option" :value="option">{{ option }}</option>
          </select>
        </label>
        <RouterLink class="btn" :to="{ name: 'leave-apply' }">Apply for leave</RouterLink>
      </div>
    </header>

    <div v-if="actionError" class="notice notice-error">{{ actionError }}</div>

    <section class="panel">
      <div class="panel-head">
        <h2>Balances</h2>
        <span class="ref">Available is already net of anything pending</span>
      </div>
      <StateBlock
        :loading="balances.loading.value"
        :error="balances.error.value"
        :empty="balanceRows.length === 0"
        empty-title="No entitlements for this year"
        empty-body="Ask HR to open your leave year."
        @retry="balances.run(year)"
      >
        <div class="balance-grid">
          <article v-for="row in balanceRows" :key="row.code" class="balance">
            <p class="eyebrow">{{ row.code }}</p>
            <p class="balance-name">{{ row.name }}</p>
            <p class="balance-figure figure">{{ row.available }}</p>
            <p class="ref">available of {{ row.granted }} granted</p>
            <div class="meter" role="img" :aria-label="`${row.taken} taken, ${row.pending} pending`">
              <span
                class="meter-taken"
                :style="{ width: `${row.granted ? Math.min(100, (row.taken / row.granted) * 100) : 0}%` }"
              ></span>
              <span
                class="meter-pending"
                :style="{ width: `${row.granted ? Math.min(100, (row.pending / row.granted) * 100) : 0}%` }"
              ></span>
            </div>
            <dl class="balance-detail">
              <div><dt>Taken</dt><dd>{{ row.taken }}</dd></div>
              <div><dt>Pending</dt><dd>{{ row.pending }}</dd></div>
              <div v-if="row.carried_forward"><dt>Carried</dt><dd>{{ row.carried_forward }}</dd></div>
            </dl>
            <p v-if="row.carry_forward_expires_on" class="ref carry-note">
              Carried days expire {{ row.carry_forward_expires_on }}
            </p>
          </article>
        </div>
      </StateBlock>
    </section>

    <section class="panel">
      <div class="panel-head"><h2>My requests</h2></div>
      <StateBlock
        :loading="applications.loading.value"
        :error="applications.error.value"
        :empty="applicationRows.length === 0"
        empty-title="No requests this year"
        empty-body="When you apply for leave it will show here with its status."
        @retry="applications.run({ year })"
      >
        <template #action>
          <RouterLink class="btn" style="margin-top: var(--s4)" :to="{ name: 'leave-apply' }">
            Apply for leave
          </RouterLink>
        </template>

        <div class="table-wrap">
          <table class="data">
            <thead>
              <tr>
                <th>Type</th>
                <th>Dates</th>
                <th class="num">Days</th>
                <th>Status</th>
                <th>Decided</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in applicationRows" :key="row.id">
                <td>
                  {{ row.leave_type_name }}
                  <span v-if="!row.is_paid" class="tag tag-neutral" style="margin-left: 0.4rem">
                    Unpaid
                  </span>
                </td>
                <td>{{ dateRange(row.start_date, row.end_date) }}</td>
                <td class="num">{{ row.working_days }}</td>
                <td>
                  <StatusTag :status="row.status" />
                  <p v-if="row.decision_note" class="ref" style="margin-top: 0.3rem">
                    {{ row.decision_note }}
                  </p>
                </td>
                <td class="ref">{{ row.decided_at ? dateTime(row.decided_at) : '—' }}</td>
                <td style="text-align: right">
                  <button
                    v-if="canCancel(row)"
                    class="btn btn-quiet btn-small"
                    :disabled="cancelling === row.id"
                    @click="cancel(row)"
                  >
                    {{ cancelling === row.id ? 'Cancelling…' : 'Cancel' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </StateBlock>
    </section>
  </div>
</template>

<style scoped>
.balance-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
}

.balance {
  padding: var(--s5);
  border-right: 1px solid var(--rule);
  border-bottom: 1px solid var(--rule);
}

.balance-name {
  font-weight: 600;
  margin-top: var(--s1);
}

.balance-figure {
  font-size: var(--step-3);
  font-weight: 500;
  line-height: 1.1;
  margin-top: var(--s3);
  color: var(--teal);
}

/* Taken and pending shown as one bar, because they cost the same balance. */
.meter {
  display: flex;
  height: 4px;
  background: var(--sunk);
  border-radius: 2px;
  overflow: hidden;
  margin: var(--s3) 0;
}

.meter-taken {
  background: var(--teal);
}

.meter-pending {
  background: var(--teal-line);
}

.balance-detail {
  display: flex;
  gap: var(--s4);
  margin: 0;
}

.balance-detail > div {
  display: flex;
  gap: 0.35rem;
  font-size: var(--step--1);
}

.balance-detail dt {
  color: var(--muted);
}

.balance-detail dd {
  margin: 0;
  font-family: var(--mono);
}

.carry-note {
  margin-top: var(--s2);
  color: var(--amber);
}
</style>
