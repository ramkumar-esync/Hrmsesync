<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useAsync } from '@/composables/useAsync'
import { employees as employeeApi, entitlements as entitlementApi } from '@/api/resources'
import { readError, readFieldErrors } from '@/api/client'
import { money, date } from '@/format'
import FormField from '@/components/FormField.vue'
import StatusTag from '@/components/StatusTag.vue'
import StateBlock from '@/components/StateBlock.vue'
import EmployeeEditModal from '@/components/EmployeeEditModal.vue'
import AppIcon from '@/components/AppIcon.vue'

const search = ref('')
const status = ref('')
const state = useAsync((params) => employeeApi.list(params))

const showForm = ref(false)
const submitting = ref(false)
const editing = ref(null)

// Opening the leave year grants everyone their annual entitlement for the year.
// HR does this once a year; the handler skips anyone already granted, so it is
// safe to click more than once.
const currentYear = new Date().getFullYear()
const granting = ref(false)

async function openLeaveYear() {
  if (!window.confirm(`Grant ${currentYear} leave entitlements to all active employees?`)) {
    return
  }
  rowError.value = null
  granting.value = true
  try {
    const result = await entitlementApi.grant({ year: currentYear })
    credential.value = null
    notice.value = result.message ?? 'Leave year opened.'
  } catch (caught) {
    rowError.value = readError(caught)
  } finally {
    granting.value = false
  }
}

// A password shown exactly once, after creating an employee or resetting one.
// Held here, never re-fetchable, and cleared when the person dismisses it.
const credential = ref(null)
const rowBusy = ref(null)
const rowError = ref(null)
const notice = ref(null)

const statusOptions = [
  { value: 'probation', label: 'Probation' },
  { value: 'confirmed', label: 'Confirmed' },
  { value: 'contract', label: 'Contract' },
  { value: 'resigned', label: 'Resigned' },
  { value: 'terminated', label: 'Terminated' },
]

async function copyCredential() {
  try {
    await navigator.clipboard.writeText(credential.value.password)
    credential.value = { ...credential.value, copied: true }
  } catch {
    // Clipboard can be blocked; the value is on screen to read regardless.
  }
}

async function changeStatus(row, event) {
  const next = event.target.value
  if (next === row.status) return

  rowError.value = null
  rowBusy.value = row.id

  // Ending employment needs a last day; default to today, let HR confirm.
  let effectiveOn
  if (next === 'resigned' || next === 'terminated') {
    const today = new Date().toISOString().slice(0, 10)
    effectiveOn = window.prompt(
      `Last working day for ${row.name}?`,
      today,
    )
    if (!effectiveOn) {
      event.target.value = row.status
      rowBusy.value = null
      return
    }
  }

  try {
    await employeeApi.changeStatus(row.id, { status: next, effective_on: effectiveOn })
    load()
  } catch (caught) {
    rowError.value = readError(caught)
    event.target.value = row.status
  } finally {
    rowBusy.value = null
  }
}

async function resetPassword(row) {
  if (!window.confirm(`Reset password for ${row.name}? A new temporary password will be generated.`)) {
    return
  }

  rowError.value = null
  rowBusy.value = row.id
  try {
    const response = await employeeApi.resetPassword(row.id)
    credential.value = {
      title: 'Password reset',
      name: row.name,
      email: row.work_email,
      password: response.data.temporary_password,
      copied: false,
    }
  } catch (caught) {
    rowError.value = readError(caught)
  } finally {
    rowBusy.value = null
  }
}
const error = ref(null)
const fieldErrors = ref({})

const blank = () => ({
  employee_number: '',
  name: '',
  work_email: '',
  joined_on: '',
  date_of_birth: '',
  job_title: '',
  department: '',
  basic_salary: '',
  fixed_allowance: '',
  role: 'employee',
  epf_number: '',
  socso_number: '',
  tax_reference_number: '',
  bank_name: '',
  bank_account_number: '',
})
const form = ref(blank())

function load() {
  state.run({ search: search.value || undefined, status: status.value || undefined }).catch(() => {})
}

onMounted(load)

let debounce
watch([search, status], () => {
  clearTimeout(debounce)
  debounce = setTimeout(load, 300)
})

const rows = computed(() => state.data.value?.data ?? [])

async function submit() {
  error.value = null
  fieldErrors.value = {}
  submitting.value = true

  try {
    const payload = Object.fromEntries(
      Object.entries(form.value).filter(([, value]) => value !== ''),
    )
    const response = await employeeApi.create(payload)
    showForm.value = false
    const created = response.data ?? response
    const tempPassword = response.meta?.temporary_password

    if (tempPassword) {
      credential.value = {
        title: 'Employee added',
        name: created.name,
        email: created.work_email,
        password: tempPassword,
        copied: false,
      }
    }

    form.value = blank()
    load()
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
        <p class="eyebrow">People</p>
        <h1 class="page-title">Employees</h1>
      </div>
      <div style="display: flex; gap: var(--s3)">
        <button class="btn btn-quiet" :disabled="granting" @click="openLeaveYear">
          {{ granting ? 'Granting…' : `Open leave year ${currentYear}` }}
        </button>
        <button class="btn" @click="showForm = !showForm">
          {{ showForm ? 'Close' : 'Add employee' }}
        </button>
      </div>
    </header>

    <div v-if="notice" class="notice">{{ notice }}</div>
    <div v-if="rowError" class="notice notice-error">{{ rowError }}</div>

    <!-- One-time credential reveal, shown after create or reset. -->
    <div v-if="credential" class="cred-overlay" @click.self="credential = null">
      <div class="cred-card panel" role="dialog" aria-modal="true">
        <div class="panel-head">
          <h2>{{ credential.title }}</h2>
          <button class="cred-close" aria-label="Dismiss" @click="credential = null">
            <AppIcon name="close" :size="18" />
          </button>
        </div>
        <div class="panel-body stack-tight">
          <p>
            A temporary password for <strong>{{ credential.name }}</strong> has been set.
            Share it with them through a secure channel — it
            <strong>will not be shown again</strong>.
          </p>
          <div class="cred-box">
            <div>
              <p class="eyebrow">Sign in at</p>
              <p class="figure cred-email">{{ credential.email }}</p>
            </div>
            <div>
              <p class="eyebrow">Temporary password</p>
              <p class="figure cred-pass">{{ credential.password }}</p>
            </div>
          </div>
          <div class="notice">
            They can change it any time from their account — they are not forced to,
            so encourage them to.
          </div>
          <div style="display: flex; justify-content: flex-end; gap: var(--s3)">
            <button class="btn btn-quiet" @click="copyCredential">
              {{ credential.copied ? 'Copied' : 'Copy password' }}
            </button>
            <button class="btn" @click="credential = null">Done</button>
          </div>
        </div>
      </div>
    </div>

    <section v-if="showForm" class="panel">
      <div class="panel-head"><h2>Add an employee</h2></div>
      <form class="panel-body" novalidate @submit.prevent="submit">
        <div v-if="error" class="notice notice-error" style="margin-bottom: var(--s5)">
          {{ error }}
        </div>

        <p class="eyebrow section-label">Identity</p>
        <div class="field-row">
          <FormField id="employee_number" label="Employee number" :error="fieldErrors.employee_number">
            <template #default="{ id }">
              <input :id="id" v-model="form.employee_number" class="control control-mono" placeholder="EMP-0006" required />
</template>
          </FormField>
          <FormField id="name" label="Full name" :error="fieldErrors.name">
            <template #default="{ id }">
              <input :id="id" v-model="form.name" class="control" required />
            </template>
          </FormField>
        </div>
        <div class="field-row">
          <FormField id="work_email" label="Work email" hint="Also becomes their sign-in." :error="fieldErrors.work_email">
            <template #default="{ id }">
              <input :id="id" v-model="form.work_email" class="control" type="email" required />
            </template>
          </FormField>
          <FormField id="role" label="Portal access" :error="fieldErrors.role">
            <template #default="{ id }">
              <select :id="id" v-model="form.role" class="control">
                <option value="employee">Employee</option>
                <option value="manager">Manager — approves their team's leave</option>
                <option value="hr_admin">HR administrator — full access</option>
              </select>
            </template>
          </FormField>
        </div>
        <div class="field-row">
          <FormField id="date_of_birth" label="Date of birth" hint="Drives statutory age bands." :error="fieldErrors.date_of_birth">
            <template #default="{ id }">
              <input :id="id" v-model="form.date_of_birth" class="control control-mono" type="date" required />
            </template>
          </FormField>
          <FormField id="joined_on" label="Join date" :error="fieldErrors.joined_on">
            <template #default="{ id }">
              <input :id="id" v-model="form.joined_on" class="control control-mono" type="date" required />
            </template>
          </FormField>
        </div>

        <p class="eyebrow section-label">Role and pay</p>
        <div class="field-row">
          <FormField id="job_title" label="Job title" :error="fieldErrors.job_title">
            <template #default="{ id }">
              <input :id="id" v-model="form.job_title" class="control" required />
            </template>
          </FormField>
          <FormField id="department" label="Department" :error="fieldErrors.department">
            <template #default="{ id }">
              <input :id="id" v-model="form.department" class="control" />
            </template>
          </FormField>
        </div>
        <div class="field-row">
          <FormField id="basic_salary" label="Basic salary (monthly)" :error="fieldErrors.basic_salary">
            <template #default="{ id }">
              <input :id="id" v-model="form.basic_salary" class="control control-mono" inputmode="decimal" placeholder="5000.00" required />
            </template>
          </FormField>
          <FormField id="fixed_allowance" label="Fixed allowance" :error="fieldErrors.fixed_allowance">
            <template #default="{ id }">
              <input :id="id" v-model="form.fixed_allowance" class="control control-mono" inputmode="decimal" placeholder="0.00" />
            </template>
          </FormField>
        </div>

        <p class="eyebrow section-label">Statutory and banking</p>
        <div class="field-row">
          <FormField id="epf_number" label="EPF number" :error="fieldErrors.epf_number">
            <template #default="{ id }">
              <input :id="id" v-model="form.epf_number" class="control control-mono" />
            </template>
          </FormField>
          <FormField id="socso_number" label="SOCSO number" :error="fieldErrors.socso_number">
            <template #default="{ id }">
              <input :id="id" v-model="form.socso_number" class="control control-mono" />
            </template>
          </FormField>
        </div>
        <div class="field-row">
          <FormField id="tax_reference_number" label="Income tax number" :error="fieldErrors.tax_reference_number">
            <template #default="{ id }">
              <input :id="id" v-model="form.tax_reference_number" class="control control-mono" />
            </template>
          </FormField>
          <FormField id="bank_name" label="Bank" :error="fieldErrors.bank_name">
            <template #default="{ id }">
              <input :id="id" v-model="form.bank_name" class="control" />
            </template>
          </FormField>
        </div>
        <FormField id="bank_account_number" label="Account number" :error="fieldErrors.bank_account_number">
          <template #default="{ id }">
            <input :id="id" v-model="form.bank_account_number" class="control control-mono" inputmode="numeric" />
          </template>
        </FormField>

        <div style="display: flex; justify-content: flex-end; gap: var(--s3)">
          <button type="button" class="btn btn-quiet" @click="showForm = false">Cancel</button>
          <button class="btn" type="submit" :disabled="submitting">
            {{ submitting ? 'Saving…' : 'Add employee' }}
          </button>
        </div>
      </form>
    </section>

    <section class="panel">
      <div class="panel-head">
        <h2>Directory</h2>
        <div style="display: flex; gap: var(--s3)">
          <input v-model="search" class="control" type="search" placeholder="Search name or number" style="width: 15rem" />
          <select v-model="status" class="control" style="width: 10rem">
            <option value="">All statuses</option>
            <option value="probation">Probation</option>
            <option value="confirmed">Confirmed</option>
            <option value="contract">Contract</option>
            <option value="resigned">Resigned</option>
            <option value="terminated">Terminated</option>
          </select>
        </div>
      </div>

      <StateBlock
        :loading="state.loading.value"
        :error="state.error.value"
        :empty="rows.length === 0"
        empty-title="No employees match"
        empty-body="Try a different search, or add someone."
        @retry="load"
      >
        <div class="table-wrap">
          <table class="data">
            <thead>
              <tr>
                <th>No.</th>
                <th>Name</th>
                <th>Position</th>
                <th>Department</th>
                <th>Joined</th>
                <th class="num">Basic salary</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody v-for="row in rows" :key="row.id">
              <tr>
                <td class="actions-cell">
                  <!-- Edit button with icon and hover tooltip -->
                  <button
                    class="btn btn-quiet btn-icon"
                    title="Edit employee"
                    aria-label="Edit employee"
                    @click="editing = row"
                  >
                    <AppIcon name="pencil" :size="16" />
                  </button>

                  <!-- Reset password button with icon and hover tooltip -->
                  <button
                    v-if="row.has_login"
                    class="btn btn-quiet btn-icon"
                    :disabled="rowBusy === row.id"
                    title="Reset password"
                    aria-label="Reset password"
                    @click="resetPassword(row)"
                  >
                    <AppIcon name="key" :size="16" />
                  </button>
                  <span v-else class="ref" title="No portal access">
                    <AppIcon name="lock" :size="16" />
                  </span>
                </td>
                <td class="figure">{{ row.employee_number }}</td>
                <td>
                  <div style="font-weight: 500">{{ row.name }}</div>
                  <div class="ref">{{ row.work_email }}</div>
                </td>
                <td>{{ row.job_title }}</td>
                <td>{{ row.department ?? '—' }}</td>
                <td class="ref">{{ date(row.joined_on) }}</td>
                <td class="num">
                  {{ money({ amount: row.basic_salary_minor / 100, currency: row.currency }, { showCurrency: false }) }}
                </td>
                <td>
                  <select
                    class="control status-select"
                    :value="row.status"
                    :disabled="rowBusy === row.id"
                    :aria-label="`Status for ${row.name}`"
                    @change="changeStatus(row, $event)"
                  >
                    <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                      {{ opt.label }}
                    </option>
                  </select>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </StateBlock>
    </section>

    <EmployeeEditModal
      v-if="editing"
      :employee="editing"
      @close="editing = null"
      @saved="editing = null; load()"
    />
  </div>
</template>

<style scoped>
.status-select {
  padding: 0.25rem 0.5rem;
  font-size: var(--step--1);
  width: auto;
  min-width: 8.5rem;
}

.cred-overlay {
  position: fixed;
  inset: 0;
  z-index: 50;
  background: rgba(23, 26, 31, 0.45);
  display: grid;
  place-items: center;
  padding: var(--s4);
}

.cred-card {
  width: 100%;
  max-width: 30rem;
  box-shadow: var(--shadow-lg);
}

.cred-close {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--muted);
  display: inline-flex;
  padding: var(--s1);
}

.cred-box {
  display: grid;
  gap: var(--s4);
  padding: var(--s4);
  background: var(--teal-tint);
  border: 1px solid var(--teal-line);
  border-radius: var(--radius-sm);
}

.cred-email {
  font-size: var(--step-0);
  margin-top: var(--s1);
}

.cred-pass {
  font-size: var(--step-2);
  font-weight: 600;
  letter-spacing: 0.02em;
  margin-top: var(--s1);
  color: var(--teal);
  user-select: all;
}

.section-label {
  padding-bottom: var(--s2);
  border-bottom: 1px solid var(--rule);
  margin-bottom: var(--s4);
}

.section-label:not(:first-child) {
  margin-top: var(--s5);
}
.actions-cell {
  display: flex;
  align-items: center;
  gap: var(--s2);
  white-space: nowrap;
}

.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.35rem;
  border-radius: var(--radius-sm, 4px);
}
</style>