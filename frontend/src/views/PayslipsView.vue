<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useAsync } from '@/composables/useAsync'
import { payslips as payslipApi } from '@/api/resources'
import { money, period, dateTime } from '@/format'
import StatusTag from '@/components/StatusTag.vue'
import StateBlock from '@/components/StateBlock.vue'
import LedgerBand from '@/components/LedgerBand.vue'

const thisYear = new Date().getFullYear()
const year = ref(thisYear)
const years = Array.from({ length: 5 }, (_, i) => thisYear - i)

const state = useAsync((selected) => payslipApi.mine(selected))

onMounted(() => state.run(year.value).catch(() => {}))
watch(year, (value) => state.run(value).catch(() => {}))

const rows = computed(() => state.data.value?.data ?? [])
const ytd = computed(() => state.data.value?.year_to_date ?? null)
const downloading = ref(null)
const downloadError = ref(null)

/*
 * Fetched with the bearer token and handed to the browser as a blob rather than
 * linked directly — payslips sit on a private disk, so the URL alone is not
 * enough to open one.
 */
async function download(slip) {
  downloading.value = slip.id
  downloadError.value = null
  try {
    const response = await payslipApi.download(slip.id)
    const url = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href = url
    link.download = `Payslip-${slip.period}.pdf`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  } catch {
    downloadError.value = 'The payslip could not be downloaded. Try again in a moment.'
  } finally {
    downloading.value = null
  }
}
</script>

<template>
  <div class="stack">
    <header class="row-between">
      <div>
        <p class="eyebrow">Payslips</p>
        <h1 class="page-title">My payslips</h1>
      </div>
      <label class="field" style="margin: 0">
        <span class="field-label">Year</span>
        <select v-model.number="year" class="control control-mono">
          <option v-for="option in years" :key="option" :value="option">{{ option }}</option>
        </select>
      </label>
    </header>

    <LedgerBand
      v-if="ytd"
      eyebrow="Net received this year"
      :figure="money(ytd.net, { showCurrency: false })"
      :caption="`${ytd.payslip_count} payslip${ytd.payslip_count === 1 ? '' : 's'} issued in ${year}`"
    >
      <template #meta>
        <div>Gross {{ money(ytd.gross, { showCurrency: false }) }}</div>
        <div>Deductions {{ money(ytd.deductions, { showCurrency: false }) }}</div>
      </template>
    </LedgerBand>

    <div v-if="downloadError" class="notice notice-error">{{ downloadError }}</div>

    <section class="panel">
      <StateBlock
        :loading="state.loading.value"
        :error="state.error.value"
        :empty="rows.length === 0"
        empty-title="No payslips for this year"
        empty-body="A payslip appears here once HR finalises the payroll run for that month."
        @retry="state.run(year)"
      >
        <div class="table-wrap">
          <table class="data">
            <thead>
              <tr>
                <th>Period</th>
                <th class="num">Gross</th>
                <th class="num">Deductions</th>
                <th class="num">Net pay</th>
                <th>Status</th>
                <th>Issued</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="slip in rows" :key="slip.id">
                <td>
                  <RouterLink :to="{ name: 'payslip', params: { id: slip.id } }">
                    {{ period(slip.period) }}
                  </RouterLink>
                </td>
                <td class="num">{{ money(slip.gross_pay, { showCurrency: false }) }}</td>
                <td class="num">{{ money(slip.total_deductions, { showCurrency: false }) }}</td>
                <td class="num" style="font-weight: 600">
                  {{ money(slip.net_pay, { showCurrency: false }) }}
                </td>
                <td><StatusTag :status="slip.status" /></td>
                <td class="ref">{{ dateTime(slip.issued_at) }}</td>
                <td style="text-align: right">
                  <button
                    class="btn btn-quiet btn-small"
                    :disabled="downloading === slip.id"
                    @click="download(slip)"
                  >
                    {{ downloading === slip.id ? 'Preparing…' : 'Download PDF' }}
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
