<script setup>
import { computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useAsync } from '@/composables/useAsync'
import {
  payslips as payslipApi,
  leave as leaveApi,
  approvals as approvalApi,
  birthdays as birthdayApi,
  holidays as holidayApi,
} from '@/api/resources'
import { money, days, period, dateRange } from '@/format'
import AppIcon from '@/components/AppIcon.vue'
import StatusTag from '@/components/StatusTag.vue'
import StateBlock from '@/components/StateBlock.vue'
import ActivityFeed from '@/components/ActivityFeed.vue'
import TodoList from '@/components/TodoList.vue'
import MiniCalendar from '@/components/MiniCalendar.vue'
import BirthdaysPanel from '@/components/BirthdaysPanel.vue'
import { useTodos } from '@/composables/useTodos'

const { dueNow, upcoming, items: todoItems, add: addTodo } = useTodos()

const auth = useAuthStore()
const year = new Date().getFullYear()

const payslipState = useAsync(() => payslipApi.mine(year))
const balanceState = useAsync(() => leaveApi.balances(year))
const pendingState = useAsync(() => approvalApi.pending())
const leaveHistory = useAsync(() => leaveApi.applications({ year }))
const birthdayState = useAsync(() => birthdayApi.upcoming(30), [])
const holidayState = useAsync(() => holidayApi.list(year), [])

onMounted(() => {
  if (auth.hasEmployeeRecord) {
    payslipState.run().catch(() => {})
    balanceState.run().catch(() => {})
    leaveHistory.run().catch(() => {})
    holidayState.run().catch(() => {})
  }
  if (auth.isApprover) pendingState.run().catch(() => {})
  if (auth.isApprover) birthdayState.run().catch(() => {})
})

const latestPayslip = computed(() => payslipState.data.value?.data?.[0] ?? null)
const ytd = computed(() => payslipState.data.value?.year_to_date ?? null)

const annualLeave = computed(() => {
  const rows = balanceState.data.value?.data ?? []
  return rows.find((row) => row.code === 'ANNUAL') ?? rows[0] ?? null
})

const otherBalances = computed(() =>
  (balanceState.data.value?.data ?? []).filter((row) => row !== annualLeave.value).slice(0, 4),
)

const awaitingCount = computed(() => (pendingState.data.value ?? []).length)
const greeting = computed(() => {
  const h = new Date().getHours()
  return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening'
})

/*
 * The activity feed is composed here from data already fetched — issued
 * payslips and decided leave — rather than a separate endpoint. Each entry is
 * shaped for the dumb ActivityFeed component and the list is sorted newest
 * first.
 */
const activity = computed(() => {
  const events = []

  for (const slip of (payslipState.data.value?.data ?? []).slice(0, 4)) {
    events.push({
      icon: 'payslip',
      tone: 'brand',
      text: `Payslip for <strong>${period(slip.period)}</strong> issued`,
      at: slip.issued_at,
    })
  }

  for (const app of (leaveHistory.data.value?.data ?? []).slice(0, 6)) {
    if (app.status === 'approved' || app.status === 'rejected') {
      events.push({
        icon: app.status === 'approved' ? 'approvals' : 'close',
        tone: app.status === 'approved' ? 'positive' : 'negative',
        text: `${app.leave_type_name} leave ${
          app.status === 'approved' ? 'approved' : 'not approved'
        } (${dateRange(app.start_date, app.end_date)})`,
        at: app.decided_at ?? app.applied_at,
      })
    } else {
      events.push({
        icon: 'leave',
        tone: 'pending',
        text: `Applied for <strong>${app.leave_type_name}</strong> leave`,
        at: app.applied_at,
      })
    }
  }

  return events
    .filter((e) => e.at)
    .sort((a, b) => new Date(b.at) - new Date(a.at))
    .slice(0, 6)
})

// Approved and pending leave, shaped as calendar ranges to tint the month grid.
const leaveRanges = computed(() =>
  (leaveHistory.data.value?.data ?? [])
    .filter((a) => ['approved', 'pending'].includes(a.status))
    .map((a) => ({
      from: a.start_date,
      to: a.end_date,
      tone: a.status === 'approved' ? 'brand' : 'pending',
      label: `${a.leave_type_name} (${a.status})`,
    })),
)

// Public holidays as single-day ranges, tinted with their own tone.
const holidayRanges = computed(() =>
  (holidayState.data.value ?? []).map((h) => ({
    from: h.date,
    to: h.date,
    tone: 'holiday',
    label: h.name,
  })),
)

// Leave first so a leave day still reads as leave if it ever overlaps a holiday
// (markFor returns the first matching range).
const calendarRanges = computed(() => {
  const taskRanges = (todoItems.value ?? [])
    .filter((t) => t.due && !t.done)
    .map((t) => {
      const day = new Date(t.due)
      const iso = `${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, '0')}-${String(day.getDate()).padStart(2, '0')}`
      return { from: iso, to: iso, tone: 'task', label: `Task: ${t.text}` }
    })

  return [...leaveRanges.value, ...holidayRanges.value, ...taskRanges]
})
</script>

<template>
  <div class="stack">
    <header>
      <p class="eyebrow">Overview</p>
      <h1 class="page-title">{{ greeting }}, {{ auth.user?.name?.split(' ')[0] }}</h1>
      <p v-if="!auth.hasEmployeeRecord" class="lede">
        This account is not linked to an employee record yet, so payslips and
        leave are unavailable. Your HR administrator can link it.
      </p>
    </header>

    <!-- Task reminder: tasks with a due date that are now due or overdue. -->
    <div v-if="dueNow.length" class="reminder">
      <span class="reminder-chip"><AppIcon name="clock" :size="16" /></span>
      <div class="reminder-body">
        <p class="reminder-title">
          {{ dueNow.length }} task{{ dueNow.length > 1 ? 's' : '' }} due
        </p>
        <p class="reminder-list">{{ dueNow.map((t) => t.text).join(' · ') }}</p>
      </div>
    </div>

    <!-- KPI tiles -->
    <div v-if="auth.hasEmployeeRecord || auth.isApprover" class="stat-grid">
      <RouterLink v-if="auth.hasEmployeeRecord" :to="{ name: 'payslips' }" class="stat">
        <span class="stat-chip"><AppIcon name="wallet" :size="20" /></span>
        <span class="stat-value">
          {{ latestPayslip ? money(latestPayslip.net_pay, { showCurrency: false }) : '—' }}
        </span>
        <span class="stat-label">Latest net pay</span>
        <span class="stat-foot">{{ latestPayslip ? period(latestPayslip.period) : 'No payslip yet' }}</span>
      </RouterLink>

      <RouterLink v-if="auth.hasEmployeeRecord" :to="{ name: 'leave' }" class="stat">
        <span class="stat-chip stat-chip-green"><AppIcon name="calendarCheck" :size="20" /></span>
        <span class="stat-value">{{ annualLeave ? annualLeave.available : '—' }}</span>
        <span class="stat-label">Annual leave available</span>
        <span class="stat-foot">
          {{ annualLeave ? `of ${annualLeave.granted} days granted` : 'No entitlement yet' }}
        </span>
      </RouterLink>

      <RouterLink v-if="auth.hasEmployeeRecord && ytd" :to="{ name: 'payslips' }" class="stat">
        <span class="stat-chip stat-chip-blue"><AppIcon name="payslip" :size="20" /></span>
        <span class="stat-value">{{ money(ytd.net, { showCurrency: false }) }}</span>
        <span class="stat-label">Net received in {{ year }}</span>
        <span class="stat-foot">{{ ytd.payslip_count }} payslips issued</span>
      </RouterLink>

      <RouterLink v-if="auth.isApprover" :to="{ name: 'approvals' }" class="stat">
        <span class="stat-chip stat-chip-amber"><AppIcon name="clock" :size="20" /></span>
        <span class="stat-value">{{ awaitingCount }}</span>
        <span class="stat-label">Awaiting your decision</span>
        <span class="stat-foot">{{ awaitingCount === 0 ? 'All clear' : 'Leave requests to review' }}</span>
      </RouterLink>
    </div>

    <!-- Activity + personal tools -->
    <div class="dash-cols">
      <div class="stack" style="min-width: 0">
        <ActivityFeed
          :items="activity"
          :loading="payslipState.loading.value || leaveHistory.loading.value"
        />
        <BirthdaysPanel
          v-if="auth.isApprover"
          :items="birthdayState.data.value ?? []"
          :loading="birthdayState.loading.value"
        />
      </div>
      <div class="stack" style="min-width: 0">
        <TodoList />
        <MiniCalendar
          v-if="auth.hasEmployeeRecord"
          title="Calendar"
          :ranges="calendarRanges"
          interactive
          @add-task="addTodo"
        />
      </div>
    </div>

    <div class="grid-2">
      <section v-if="auth.hasEmployeeRecord" class="panel">
        <div class="panel-head">
          <h2>Recent payslips</h2>
          <RouterLink :to="{ name: 'payslips' }">All payslips</RouterLink>
        </div>
        <StateBlock
          :loading="payslipState.loading.value"
          :error="payslipState.error.value"
          :empty="!(payslipState.data.value?.data?.length)"
          empty-title="No payslips yet"
          empty-body="They appear here as soon as HR finalises a payroll run."
          @retry="payslipState.run()"
        >
          <div class="panel-body">
            <RouterLink
              v-for="slip in payslipState.data.value.data.slice(0, 4)"
              :key="slip.id"
              class="ledger-link"
              :to="{ name: 'payslip', params: { id: slip.id } }"
            >
              <span class="label">{{ period(slip.period) }}</span>
              <span class="leader" aria-hidden="true"></span>
              <span class="amount">{{ money(slip.net_pay, { showCurrency: false }) }}</span>
              <AppIcon name="chevron" :size="16" class="chev" />
            </RouterLink>
          </div>
        </StateBlock>
      </section>

      <section v-if="auth.hasEmployeeRecord" class="panel">
        <div class="panel-head">
          <h2>Leave balances</h2>
          <RouterLink :to="{ name: 'leave' }">Manage leave</RouterLink>
        </div>
        <StateBlock
          :loading="balanceState.loading.value"
          :error="balanceState.error.value"
          :empty="!otherBalances.length && !annualLeave"
          empty-title="No entitlements granted"
          empty-body="Ask HR to open your leave year."
          @retry="balanceState.run()"
        >
          <div class="panel-body">
            <div v-for="row in otherBalances" :key="row.code" class="ledger-row">
              <span class="label">{{ row.name }}</span>
              <span class="leader" aria-hidden="true"></span>
              <span class="amount">{{ row.available }}</span>
            </div>
            <p v-if="!otherBalances.length" class="ref">Annual leave shown above.</p>
          </div>
        </StateBlock>
      </section>
    </div>

    <section v-if="auth.isApprover" class="panel">
      <div class="panel-head">
        <h2>Waiting on you</h2>
        <RouterLink :to="{ name: 'approvals' }">Open approvals</RouterLink>
      </div>
      <StateBlock
        :loading="pendingState.loading.value"
        :error="pendingState.error.value"
        :empty="awaitingCount === 0"
        empty-title="Nothing to decide"
        empty-body="Leave requests from your team will appear here."
        @retry="pendingState.run()"
      >
        <div class="table-wrap">
          <table class="data">
            <thead>
              <tr>
                <th>Employee</th>
                <th>Type</th>
                <th>Dates</th>
                <th class="num">Days</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in pendingState.data.value.slice(0, 5)" :key="row.id">
                <td>{{ row.employee_name }}</td>
                <td>{{ row.leave_type_name }}</td>
                <td>{{ dateRange(row.start_date, row.end_date) }}</td>
                <td class="num">{{ row.working_days }}</td>
                <td><StatusTag :status="row.status" /></td>
              </tr>
            </tbody>
          </table>
        </div>
      </StateBlock>
    </section>

    <section v-if="auth.hasEmployeeRecord && ytd" class="panel">
      <div class="panel-head">
        <h2>Year to date · {{ year }}</h2>
        <span class="ref">{{ ytd.payslip_count }} payslips issued</span>
      </div>
      <div class="panel-body">
        <div class="ledger-row">
          <span class="label">Gross pay</span>
          <span class="leader" aria-hidden="true"></span>
          <span class="amount">{{ money(ytd.gross, { showCurrency: false }) }}</span>
        </div>
        <div class="ledger-row">
          <span class="label">Total deductions</span>
          <span class="leader" aria-hidden="true"></span>
          <span class="amount">{{ money(ytd.deductions, { showCurrency: false }) }}</span>
        </div>
        <div class="ledger-row is-total">
          <span class="label">Net received</span>
          <span class="leader" aria-hidden="true"></span>
          <span class="amount">{{ money(ytd.net, { showCurrency: false }) }}</span>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.reminder {
  display: flex;
  gap: var(--s3);
  align-items: center;
  padding: var(--s3) var(--s4);
  background: var(--amber-wash);
  border: 1px solid var(--amber-line, #f0d9a8);
  border-radius: var(--radius);
}

.reminder-chip {
  flex: 0 0 34px;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  background: #fff;
  color: var(--amber);
}

.reminder-title {
  font-weight: 600;
  font-size: var(--step-0);
  color: var(--amber);
}

.reminder-list {
  font-size: var(--step--1);
  color: var(--ink-soft);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 60ch;
}

.dash-cols {
  display: grid;
  grid-template-columns: 1.4fr 1fr;
  gap: var(--s5);
  align-items: start;
}

@media (max-width: 900px) {
  .dash-cols {
    grid-template-columns: 1fr;
  }
}

.ledger-link {
  display: flex;
  align-items: baseline;
  gap: var(--s3);
  padding: var(--s3) 0;
  border-bottom: 1px solid var(--rule);
  text-decoration: none;
  color: var(--ink);
}

.ledger-link:last-child {
  border-bottom: 0;
}

.ledger-link:hover {
  text-decoration: none;
}

.ledger-link:hover .label {
  color: var(--teal);
}

.ledger-link .label {
  flex: 0 1 auto;
}

.ledger-link .leader {
  flex: 1 1 auto;
  min-width: var(--s5);
  border-bottom: 1px dotted var(--rule-strong);
  transform: translateY(-0.25em);
}

.ledger-link .amount {
  font-family: var(--mono);
  font-variant-numeric: tabular-nums;
}

.ledger-link .chev {
  color: var(--faint);
  align-self: center;
}
</style>