<script setup>
import { ref, watch } from 'vue'
import { employees as employeeApi } from '@/api/resources'
import { readError, readFieldErrors } from '@/api/client'
import FormField from '@/components/FormField.vue'
import AppIcon from '@/components/AppIcon.vue'

/*
 * Edit an existing employee. Profile fields (name, title, department, bank) and
 * salary are saved through their own endpoints — the two are separate concerns
 * server-side, and this dialog just presents them together. Employee number,
 * email and status are shown read-only here; number and email don't change, and
 * status has its own control on the row.
 */
const props = defineProps({
  employee: { type: Object, required: true },
})
const emit = defineEmits(['close', 'saved'])

const form = ref({
  name: '',
  job_title: '',
  department: '',
  bank_name: '',
  bank_account_number: '',
  basic_salary: '',
  fixed_allowance: '',
})
const saving = ref(false)
const error = ref(null)
const fieldErrors = ref({})

watch(
  () => props.employee,
  (e) => {
    if (!e) return
    form.value = {
      name: e.name ?? '',
      job_title: e.job_title ?? '',
      department: e.department ?? '',
      bank_name: e.bank_account?.bank_name ?? '',
      bank_account_number: '',
      basic_salary:
        e.compensation?.basic_salary?.amount ??
        (e.basic_salary_minor != null ? (e.basic_salary_minor / 100).toFixed(2) : ''),
      fixed_allowance: e.compensation?.fixed_allowance?.amount ?? '',
    }
  },
  { immediate: true },
)

async function save() {
  error.value = null
  fieldErrors.value = {}
  saving.value = true

  try {
    // Profile first, then salary — two endpoints, one Save for the user.
    await employeeApi.updateProfile(props.employee.id, {
      name: form.value.name,
      job_title: form.value.job_title,
      department: form.value.department || null,
      bank_name: form.value.bank_name || null,
      bank_account_number: form.value.bank_account_number || null,
    })

    if (form.value.basic_salary !== '') {
      await employeeApi.updateCompensation(props.employee.id, {
        basic_salary: form.value.basic_salary,
        fixed_allowance: form.value.fixed_allowance || null,
      })
    }

    emit('saved')
  } catch (caught) {
    error.value = readError(caught)
    fieldErrors.value = readFieldErrors(caught)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="edit-overlay" @click.self="emit('close')">
    <div class="edit-card panel" role="dialog" aria-modal="true">
      <div class="panel-head">
        <div>
          <h2>Edit employee</h2>
          <p class="ref">{{ employee.employee_number }} · {{ employee.work_email }}</p>
        </div>
        <button class="edit-close" aria-label="Close" @click="emit('close')">
          <AppIcon name="close" :size="18" />
        </button>
      </div>

      <form class="panel-body" novalidate @submit.prevent="save">
        <div v-if="error" class="notice notice-error" style="margin-bottom: var(--s5)">
          {{ error }}
        </div>

        <p class="eyebrow section-label">Identity</p>
        <FormField id="e-name" label="Full name" :error="fieldErrors.name">
          <template #default="{ id }">
            <input :id="id" v-model="form.name" class="control" required />
          </template>
        </FormField>
        <div class="field-row">
          <FormField id="e-title" label="Job title" :error="fieldErrors.job_title">
            <template #default="{ id }">
              <input :id="id" v-model="form.job_title" class="control" required />
            </template>
          </FormField>
          <FormField id="e-dept" label="Department" :error="fieldErrors.department">
            <template #default="{ id }">
              <input :id="id" v-model="form.department" class="control" />
            </template>
          </FormField>
        </div>

        <p class="eyebrow section-label">Pay</p>
        <div class="field-row">
          <FormField id="e-salary" label="Basic salary (monthly)" :error="fieldErrors.basic_salary">
            <template #default="{ id }">
              <input
                :id="id"
                v-model="form.basic_salary"
                class="control control-mono"
                inputmode="decimal"
                placeholder="5000.00"
              />
            </template>
          </FormField>
          <FormField id="e-allow" label="Fixed allowance" :error="fieldErrors.fixed_allowance">
            <template #default="{ id }">
              <input
                :id="id"
                v-model="form.fixed_allowance"
                class="control control-mono"
                inputmode="decimal"
                placeholder="0.00"
              />
            </template>
          </FormField>
        </div>

        <p class="eyebrow section-label">Bank</p>
        <div class="field-row">
          <FormField id="e-bank" label="Bank" :error="fieldErrors.bank_name">
            <template #default="{ id }">
              <input :id="id" v-model="form.bank_name" class="control" />
            </template>
          </FormField>
          <FormField
            id="e-acct"
            label="Account number"
            hint="Leave blank to keep the current account."
            :error="fieldErrors.bank_account_number"
          >
            <template #default="{ id }">
              <input
                :id="id"
                v-model="form.bank_account_number"
                class="control control-mono"
                inputmode="numeric"
                placeholder="••••••••"
              />
            </template>
          </FormField>
        </div>

        <div class="edit-actions">
          <button type="button" class="btn btn-quiet" @click="emit('close')">Cancel</button>
          <button class="btn" type="submit" :disabled="saving">
            {{ saving ? 'Saving…' : 'Save changes' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.edit-overlay {
  position: fixed;
  inset: 0;
  z-index: 50;
  background: rgba(23, 26, 31, 0.45);
  display: grid;
  place-items: center;
  padding: var(--s4);
  overflow-y: auto;
}

.edit-card {
  width: 100%;
  max-width: 34rem;
  box-shadow: var(--shadow-lg);
  margin: auto;
}

.edit-close {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--muted);
  display: inline-flex;
  padding: var(--s1);
}

.section-label {
  padding-bottom: var(--s2);
  border-bottom: 1px solid var(--rule);
  margin-bottom: var(--s4);
}

.section-label:not(:first-child) {
  margin-top: var(--s5);
}

.edit-actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--s3);
  margin-top: var(--s5);
  padding-top: var(--s4);
  border-top: 1px solid var(--rule);
}
</style>
