<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAsync } from '@/composables/useAsync'
import { payrollRuns as runApi } from '@/api/resources'
import { readError, readFieldErrors } from '@/api/client'
import { money, period, date } from '@/format'
import FormField from '@/components/FormField.vue'
import StatusTag from '@/components/StatusTag.vue'
import StateBlock from '@/components/StateBlock.vue'

const router = useRouter()
const state = useAsync((params) => runApi.list(params))

const showForm = ref(false)
const submitting = ref(false)
const error = ref(null)
const fieldErrors = ref({})

function defaultPeriod() {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
}

function defaultPaymentDate() {
  const now = new Date()
  const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0)
  return lastDay.toISOString().slice(0, 10)
}

const form = ref({ period: defaultPeriod(), payment_date: defaultPaymentDate(), notes: '' })

onMounted(() => state.run().catch(() => {}))

const rows = computed(() => state.data.value?.data ?? [])

async function submit() {
  error.value = null
  fieldErrors.value = {}
  submitting.value = true

  try {
    const run = await runApi.open({
      period: form.value.period,
      payment_date: form.value.payment_date,
      notes: form.value.notes || undefined,
    })
    router.push({ name: 'hr-payroll-run', params: { id: run.id } })
  } catch (caught) {
    error.value = readError(caught)
    fieldErrors.value = readFieldErrors(caught)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="stack">
    <header class="row-between">
      <div>
        <p class="eyebrow">Payroll</p>
        <h1 class="page-title">Payroll runs</h1>
        <p class="lede">
          One run per month. A run stays editable until it is finalised — after
          that, payslips are issued and cannot be changed.
        </p>
      </div>
      <button class="btn" @click="showForm = !showForm">
        {{ showForm ? 'Close' : 'Open a run' }}
      </button>
    </header>

    <section v-if="showForm" class="panel">
      <div class="panel-head"><h2>Open a payroll run</h2></div>
      <form class="panel-body" novalidate @submit.prevent="submit">
        <div v-if="error" class="notice notice-error" style="margin-bottom: var(--s5)">
          {{ error }}
        </div>

        <div class="field-row">
          <FormField id="period" label="Pay period" hint="Format YYYY-MM." :error="fieldErrors.period">
            <template #default="{ id }">
              <input :id="id" v-model="form.period" class="control control-mono" placeholder="2026-07" required />
            </template>
          </FormField>
          <FormField id="payment_date" label="Payment date" :error="fieldErrors.payment_date">
            <template #default="{ id }">
              <input :id="id" v-model="form.payment_date" class="control control-mono" type="date" required />
            </template>
          </FormField>
        </div>

        <FormField id="notes" label="Notes" hint="Optional, visible to HR only." :error="fieldErrors.notes">
          <template #default="{ id }">
            <input :id="id" v-model="form.notes" class="control" />
          </template>
        </FormField>

        <div style="display: flex; justify-content: flex-end; gap: var(--s3)">
          <button type="button" class="btn btn-quiet" @click="showForm = false">Cancel</button>
          <button class="btn" type="submit" :disabled="submitting">
            {{ submitting ? 'Opening…' : 'Open run' }}
          </button>
        </div>
      </form>
    </section>

    <section class="panel">
      <StateBlock
        :loading="state.loading.value"
        :error="state.error.value"
        :empty="rows.length === 0"
        empty-title="No payroll runs yet"
        empty-body="Open one for the current month to get started."
        @retry="state.run()"
      >
        <div class="table-wrap">
          <table class="data">
            <thead>
              <tr>
                <th>Period</th>
                <th>Status</th>
                <th>Payment date</th>
                <th class="num">Payslips</th>
                <th class="num">Total net pay</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.id">
                <td>
                  <RouterLink :to="{ name: 'hr-payroll-run', params: { id: row.id } }">
                    {{ period(row.period) }}
                  </RouterLink>
                </td>
                <td><StatusTag :status="row.status" /></td>
                <td class="ref">{{ date(row.payment_date) }}</td>
                <td class="num">{{ row.payslip_count }}</td>
                <td class="num">
                  {{ money({ amount: row.total_net_pay_minor / 100, currency: row.currency }, { showCurrency: false }) }}
                </td>
                <td style="text-align: right">
                  <RouterLink class="btn btn-quiet btn-small" :to="{ name: 'hr-payroll-run', params: { id: row.id } }">
                    Open
                  </RouterLink>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </StateBlock>
    </section>
  </div>
</template>
