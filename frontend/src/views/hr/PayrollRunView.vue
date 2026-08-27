<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAsync } from '@/composables/useAsync'
import { payrollRuns as runApi, employees as employeeApi } from '@/api/resources'
import { readError } from '@/api/client'
import { money, period, date, dateTime } from '@/format'
import LedgerBand from '@/components/LedgerBand.vue'
import StatusTag from '@/components/StatusTag.vue'
import StateBlock from '@/components/StateBlock.vue'
import FormField from '@/components/FormField.vue'

const props = defineProps({ id: { type: String, required: true } })

const run = useAsync(() => runApi.show(props.id))
const payslips = useAsync(() => runApi.payslips(props.id), [])
const staff = useAsync(() => employeeApi.list({ status: 'confirmed', per_page: 100 }))

const busy = ref(null)
const error = ref(null)
const notice = ref(null)
const confirmFinalise = ref(false)

const showEntry = ref(false)
const entry = ref({
  employee_id: '',
  use_contractual_salary: true,
  earnings: [],
  deductions: [],
  remarks: '',
})

onMounted(() => {
  run.run().catch(() => {})
  payslips.run().catch(() => {})
  staff.run().catch(() => {})
})

const rows = computed(() => payslips.data.value ?? [])
const editable = computed(() => run.data.value?.editable === true)

const totals = computed(() =>
  rows.value.reduce(
    (carry, row) => ({
      gross: carry.gross + Number(row.gross_pay),
      deductions: carry.deductions + Number(row.total_deductions),
      net: carry.net + Number(row.net_pay),
    }),
    { gross: 0, deductions: 0, net: 0 },
  ),
)

function reload() {
  run.run().catch(() => {})
  payslips.run().catch(() => {})
}

async function act(name, fn, message) {
  error.value = null
  notice.value = null
  busy.value = name
  try {
    await fn()
    notice.value = message
    reload()
  } catch (caught) {
    error.value = readError(caught)
  } finally {
    busy.value = null
  }
}

const populate = () =>
  act('populate', () => runApi.populate(props.id), 'Everyone employed this period was added.')

const finalise = () =>
  act('finalise', () => runApi.finalise(props.id), 'Run finalised. Payslips are now visible to employees.')

const markPaid = () => act('paid', () => runApi.markPaid(props.id, {}), 'Run marked as paid.')

const removePayslip = (payslipId) =>
  act('remove', () => runApi.removePayslip(props.id, payslipId), 'Payslip removed from this run.')

function addLine(collection, defaultType) {
  entry.value[collection].push({ type: defaultType, amount: '', description: '' })
}

function removeLine(collection, index) {
  entry.value[collection].splice(index, 1)
}

async function submitEntry() {
  error.value = null
  busy.value = 'entry'

  try {
    await runApi.recordEntry(props.id, {
      employee_id: entry.value.employee_id,
      use_contractual_salary: entry.value.use_contractual_salary,
      earnings: entry.value.earnings.filter((line) => line.amount !== ''),
      deductions: entry.value.deductions.filter((line) => line.amount !== ''),
      remarks: entry.value.remarks || undefined,
    })
    notice.value = 'Pay entry saved and recalculated.'
    entry.value = {
      employee_id: '',
      use_contractual_salary: true,
      earnings: [],
      deductions: [],
      remarks: '',
    }
    showEntry.value = false
    reload()
  } catch (caught) {
    error.value = readError(caught)
  } finally {
    busy.value = null
  }
}
</script>

<template>
  <div class="stack">
    <StateBlock :loading="run.loading.value" :error="run.error.value" :rows="4" @retry="run.run()">
      <template v-if="run.data.value">
        <header class="row-between">
          <div>
            <RouterLink :to="{ name: 'hr-payroll' }" class="ref">← Payroll runs</RouterLink>
            <h1 class="page-title" style="margin-top: var(--s2)">
              {{ period(run.data.value.period) }}
            </h1>
            <p class="ref">
              Payment date {{ date(run.data.value.payment_date) }}
              <span v-if="run.data.value.finalised_at">
                · finalised {{ dateTime(run.data.value.finalised_at) }}
              </span>
            </p>
          </div>
          <StatusTag :status="run.data.value.status" />
        </header>

        <div v-if="notice" class="notice">{{ notice }}</div>
        <div v-if="error" class="notice notice-error">{{ error }}</div>

        <LedgerBand
          eyebrow="Total net pay"
          :figure="money(run.data.value.totals.net_pay, { showCurrency: false })"
          :caption="`${rows.length} payslip${rows.length === 1 ? '' : 's'} in this run`"
        >
          <template #meta>
            <div>Employer cost {{ money(run.data.value.totals.employer_cost, { showCurrency: false }) }}</div>
            <div>{{ run.data.value.totals.net_pay?.currency }}</div>
          </template>
        </LedgerBand>

        <!-- The run's workflow, laid out in the order it actually happens. -->
        <section class="panel">
          <div class="panel-head">
            <h2>Run actions</h2>
            <span class="ref">{{ editable ? 'Draft — still editable' : 'Locked' }}</span>
          </div>
          <div class="panel-body steps">
            <div class="step">
              <p class="eyebrow">Step 1</p>
              <p class="step-title">Add everyone</p>
              <p class="step-body">
                Creates a draft payslip at contractual salary for everyone employed
                during {{ period(run.data.value.period) }}. Safe to run twice.
              </p>
              <button class="btn btn-quiet btn-small" :disabled="!editable || busy === 'populate'" @click="populate">
                {{ busy === 'populate' ? 'Adding…' : 'Add active staff' }}
              </button>
            </div>

            <div class="step">
              <p class="eyebrow">Step 2</p>
              <p class="step-title">Adjust the exceptions</p>
              <p class="step-body">
                Overtime, bonuses, advances, and the PCB figure from your LHDN
                calculator. Statutory lines are worked out for you.
              </p>
              <button class="btn btn-quiet btn-small" :disabled="!editable" @click="showEntry = !showEntry">
                {{ showEntry ? 'Close entry form' : 'Enter pay for someone' }}
              </button>
            </div>

            <div class="step">
              <p class="eyebrow">Step 3</p>
              <p class="step-title">Finalise</p>
              <p class="step-body">
                Issues every payslip and locks the run. Employees can download
                immediately. This cannot be undone.
              </p>
              <button
                v-if="!confirmFinalise"
                class="btn btn-small"
                :disabled="!editable || rows.length === 0"
                @click="confirmFinalise = true"
              >
                Finalise run
              </button>
              <div v-else class="confirm">
                <p class="confirm-text">
                  Finalise {{ rows.length }} payslips totalling
                  <strong class="figure">{{ money(run.data.value.totals.net_pay) }}</strong>?
                  Nothing in this run can be changed afterwards.
                </p>
                <div style="display: flex; gap: var(--s2)">
                  <button class="btn btn-quiet btn-small" @click="confirmFinalise = false">
                    Not yet
                  </button>
                  <button class="btn btn-small" :disabled="busy === 'finalise'" @click="finalise">
                    {{ busy === 'finalise' ? 'Finalising…' : 'Yes, finalise' }}
                  </button>
                </div>
              </div>
            </div>

            <div class="step">
              <p class="eyebrow">Step 4</p>
              <p class="step-title">Record payment</p>
              <p class="step-body">Mark the run as paid once the bank transfer has cleared.</p>
              <button
                class="btn btn-quiet btn-small"
                :disabled="run.data.value.status !== 'finalised' || busy === 'paid'"
                @click="markPaid"
              >
                {{ busy === 'paid' ? 'Saving…' : 'Mark as paid' }}
              </button>
            </div>
          </div>
        </section>

        <section v-if="showEntry" class="panel">
          <div class="panel-head">
            <h2>Enter pay</h2>
            <span class="ref">Re-entering for the same person overwrites their draft</span>
          </div>
          <form class="panel-body" novalidate @submit.prevent="submitEntry">
            <FormField id="employee_id" label="Employee">
              <template #default="{ id }">
                <select :id="id" v-model="entry.employee_id" class="control" required>
                  <option value="" disabled>Choose an employee</option>
                  <option v-for="person in staff.data.value?.data ?? []" :key="person.id" :value="person.id">
                    {{ person.employee_number }} — {{ person.name }}
                  </option>
                </select>
              </template>
            </FormField>

            <label class="checkline">
              <input v-model="entry.use_contractual_salary" type="checkbox" />
              <span>Include their contractual basic salary and fixed allowance</span>
            </label>

            <div class="lines">
              <div class="lines-head">
                <p class="eyebrow">Additional earnings</p>
                <button type="button" class="btn btn-quiet btn-small" @click="addLine('earnings', 'overtime')">
                  Add line
                </button>
              </div>
              <p v-if="!entry.earnings.length" class="ref">None. Add overtime, a bonus or a claim.</p>
              <div v-for="(line, index) in entry.earnings" :key="`e${index}`" class="line">
                <select v-model="line.type" class="control">
                  <option value="overtime">Overtime</option>
                  <option value="bonus">Bonus</option>
                  <option value="commission">Commission</option>
                  <option value="claim">Claim (not statutory wages)</option>
                  <option value="other_earning">Other earning</option>
                </select>
                <input v-model="line.amount" class="control control-mono" inputmode="decimal" placeholder="0.00" />
                <input v-model="line.description" class="control" placeholder="Description (optional)" />
                <button type="button" class="btn btn-quiet btn-small" @click="removeLine('earnings', index)">
                  Remove
                </button>
              </div>
            </div>

            <div class="lines">
              <div class="lines-head">
                <p class="eyebrow">Manual deductions</p>
                <button type="button" class="btn btn-quiet btn-small" @click="addLine('deductions', 'pcb')">
                  Add line
                </button>
              </div>
              <p class="ref">
                EPF, SOCSO and EIS are calculated automatically. PCB is entered here
                from your own LHDN calculation.
              </p>
              <div v-for="(line, index) in entry.deductions" :key="`d${index}`" class="line">
                <select v-model="line.type" class="control">
                  <option value="pcb">PCB (monthly tax)</option>
                  <option value="advance">Salary advance</option>
                  <option value="other_deduction">Other deduction</option>
                </select>
                <input v-model="line.amount" class="control control-mono" inputmode="decimal" placeholder="0.00" />
                <input v-model="line.description" class="control" placeholder="Description (optional)" />
                <button type="button" class="btn btn-quiet btn-small" @click="removeLine('deductions', index)">
                  Remove
                </button>
              </div>
            </div>

            <FormField id="remarks" label="Remarks" hint="Printed on the payslip.">
              <template #default="{ id }">
                <input :id="id" v-model="entry.remarks" class="control" />
              </template>
            </FormField>

            <div style="display: flex; justify-content: flex-end; gap: var(--s3)">
              <button type="button" class="btn btn-quiet" @click="showEntry = false">Cancel</button>
              <button class="btn" type="submit" :disabled="busy === 'entry' || !entry.employee_id">
                {{ busy === 'entry' ? 'Saving…' : 'Save entry' }}
              </button>
            </div>
          </form>
        </section>

        <section class="panel">
          <div class="panel-head">
            <h2>Register</h2>
            <span class="ref">
              Gross {{ money(totals.gross, { showCurrency: false }) }} · Deductions
              {{ money(totals.deductions, { showCurrency: false }) }}
            </span>
          </div>
          <StateBlock
            :loading="payslips.loading.value"
            :error="payslips.error.value"
            :empty="rows.length === 0"
            empty-title="No payslips in this run"
            empty-body="Start by adding everyone employed during this period."
            @retry="payslips.run()"
          >
            <div class="table-wrap">
              <table class="data">
                <thead>
                  <tr>
                    <th>No.</th>
                    <th>Employee</th>
                    <th class="num">Gross</th>
                    <th class="num">Deductions</th>
                    <th class="num">Net pay</th>
                    <th>Status</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in rows" :key="row.id">
                    <td class="figure">{{ row.employee_number }}</td>
                    <td>{{ row.employee_name }}</td>
                    <td class="num">{{ money(row.gross_pay, { showCurrency: false }) }}</td>
                    <td class="num">{{ money(row.total_deductions, { showCurrency: false }) }}</td>
                    <td class="num" style="font-weight: 600">
                      {{ money(row.net_pay, { showCurrency: false }) }}
                    </td>
                    <td><StatusTag :status="row.status" /></td>
                    <td style="text-align: right">
                      <button
                        v-if="editable"
                        class="btn btn-quiet btn-small"
                        :disabled="busy === 'remove'"
                        @click="removePayslip(row.id)"
                      >
                        Remove
                      </button>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="2" style="font-weight: 600">Totals</td>
                    <td class="num">{{ money(totals.gross, { showCurrency: false }) }}</td>
                    <td class="num">{{ money(totals.deductions, { showCurrency: false }) }}</td>
                    <td class="num" style="font-weight: 600">
                      {{ money(totals.net, { showCurrency: false }) }}
                    </td>
                    <td colspan="2"></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </StateBlock>
        </section>
      </template>
    </StateBlock>
  </div>
</template>

<style scoped>
.steps {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: var(--s5);
}

.step {
  padding-left: var(--s4);
  border-left: 2px solid var(--teal-line);
}

.step-title {
  font-weight: 600;
  margin-top: var(--s1);
}

.step-body {
  font-size: var(--step--1);
  color: var(--muted);
  margin: var(--s2) 0 var(--s3);
}

.confirm {
  padding: var(--s3);
  background: var(--amber-wash);
  border-radius: var(--radius);
}

.confirm-text {
  font-size: var(--step--1);
  color: #6b4605;
  margin-bottom: var(--s3);
}

.checkline {
  display: flex;
  align-items: center;
  gap: var(--s2);
  font-size: var(--step--1);
  margin-bottom: var(--s5);
}

.lines {
  padding: var(--s4);
  background: var(--sunk);
  border-radius: var(--radius);
  margin-bottom: var(--s5);
}

.lines-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--s3);
}

.line {
  display: grid;
  grid-template-columns: 12rem 8rem 1fr auto;
  gap: var(--s2);
  margin-top: var(--s2);
}

table.data tfoot td {
  border-top: 1px solid var(--rule-strong);
  padding: var(--s4) var(--s5);
}

@media (max-width: 760px) {
  .line {
    grid-template-columns: 1fr;
  }
}
</style>
