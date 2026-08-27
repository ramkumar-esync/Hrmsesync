<script setup>
import { computed, onMounted } from 'vue'
import { useAsync } from '@/composables/useAsync'
import { payslips as payslipApi } from '@/api/resources'
import { money, period, dateTime, shortRef } from '@/format'
import LedgerBand from '@/components/LedgerBand.vue'
import LedgerRow from '@/components/LedgerRow.vue'
import StatusTag from '@/components/StatusTag.vue'
import StateBlock from '@/components/StateBlock.vue'

const props = defineProps({ id: { type: String, required: true } })

const state = useAsync(() => payslipApi.show(props.id))
onMounted(() => state.run().catch(() => {}))

const slip = computed(() => state.data.value)
const employee = computed(() => slip.value?.employee ?? {})

async function download() {
  const response = await payslipApi.download(props.id)
  const url = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
  const link = document.createElement('a')
  link.href = url
  link.download = `Payslip-${slip.value.period}.pdf`
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <div class="stack">
    <StateBlock :loading="state.loading.value" :error="state.error.value" :rows="5" @retry="state.run()">
      <template v-if="slip">
        <header class="row-between">
          <div>
            <RouterLink :to="{ name: 'payslips' }" class="ref">← All payslips</RouterLink>
            <h1 class="page-title" style="margin-top: var(--s2)">{{ period(slip.period) }}</h1>
            <p class="ref">
              Reference {{ shortRef(slip.id) }} · Issued {{ dateTime(slip.issued_at) }}
            </p>
          </div>
          <div style="display: flex; gap: var(--s3); align-items: center">
            <StatusTag :status="slip.status" />
            <button class="btn" @click="download">Download PDF</button>
          </div>
        </header>

        <LedgerBand
          eyebrow="Net pay"
          :figure="money(slip.totals.net_pay, { showCurrency: false })"
          :caption="`Paid to ${employee.bank_name ?? 'your registered account'} ${employee.bank_account_masked ?? ''}`"
          :meta="slip.totals.net_pay?.currency"
        />

        <div class="grid-2">
          <section class="panel">
            <div class="panel-head"><h2>Earnings</h2></div>
            <div class="panel-body">
              <LedgerRow
                v-for="(line, index) in slip.earnings"
                :key="`e${index}`"
                :label="line.label"
                :amount="money(line.amount, { showCurrency: false })"
              />
              <LedgerRow
                total
                label="Gross pay"
                :amount="money(slip.totals.gross_pay, { showCurrency: false })"
              />
            </div>
          </section>

          <section class="panel">
            <div class="panel-head"><h2>Deductions</h2></div>
            <div class="panel-body">
              <p v-if="!slip.deductions.length" class="ref">No deductions this period.</p>
              <LedgerRow
                v-for="(line, index) in slip.deductions"
                :key="`d${index}`"
                :label="line.label"
                :note="line.system_generated ? 'calculated' : null"
                :amount="money(line.amount, { showCurrency: false })"
              />
              <LedgerRow
                total
                label="Total deductions"
                :amount="money(slip.totals.total_deductions, { showCurrency: false })"
              />
            </div>
          </section>
        </div>

        <section v-if="slip.employer_contributions.length" class="panel">
          <div class="panel-head">
            <h2>Paid by your employer</h2>
            <span class="ref">Not deducted from your pay</span>
          </div>
          <div class="panel-body">
            <LedgerRow
              v-for="(line, index) in slip.employer_contributions"
              :key="`c${index}`"
              :label="line.label"
              :amount="money(line.amount, { showCurrency: false })"
            />
            <LedgerRow
              total
              label="Cost to employer"
              :amount="money(slip.totals.employer_cost, { showCurrency: false })"
            />
          </div>
        </section>

        <section class="panel">
          <div class="panel-head"><h2>Your details on this payslip</h2></div>
          <div class="panel-body">
            <dl class="particulars">
              <div><dt>Name</dt><dd>{{ employee.name }}</dd></div>
              <div><dt>Employee no.</dt><dd class="figure">{{ employee.employee_number }}</dd></div>
              <div><dt>Position</dt><dd>{{ employee.job_title }}</dd></div>
              <div><dt>Department</dt><dd>{{ employee.department ?? '—' }}</dd></div>
              <div><dt>EPF no.</dt><dd class="figure">{{ employee.epf_number ?? '—' }}</dd></div>
              <div><dt>SOCSO no.</dt><dd class="figure">{{ employee.socso_number ?? '—' }}</dd></div>
              <div>
                <dt>Income tax no.</dt>
                <dd class="figure">{{ employee.tax_reference_number ?? '—' }}</dd>
              </div>
              <div><dt>Bank</dt><dd class="figure">{{ employee.bank_account_masked ?? '—' }}</dd></div>
            </dl>
            <p class="ref" style="margin-top: var(--s4)">
              These details are a copy taken when the payslip was issued, so this
              record does not change if your information is updated later.
            </p>
          </div>
        </section>

        <div v-if="slip.remarks" class="notice">
          <span class="eyebrow">Remarks</span>
          <p style="margin-top: var(--s2)">{{ slip.remarks }}</p>
        </div>
      </template>
    </StateBlock>
  </div>
</template>

<style scoped>
.particulars {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 0 var(--s6);
  margin: 0;
}

.particulars > div {
  display: flex;
  gap: var(--s3);
  align-items: baseline;
  padding: var(--s3) 0;
  border-bottom: 1px solid var(--rule);
}

.particulars dt {
  flex: 0 0 8.5rem;
  color: var(--muted);
  font-size: var(--step--1);
}

.particulars dd {
  margin: 0;
  font-weight: 500;
}
</style>
