<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAsync } from '@/composables/useAsync'
import { leave as leaveApi } from '@/api/resources'
import { readError, readFieldErrors } from '@/api/client'
import FormField from '@/components/FormField.vue'
import StateBlock from '@/components/StateBlock.vue'

const router = useRouter()
const types = useAsync(() => leaveApi.types(), [])
const balances = useAsync(() => leaveApi.balances(new Date().getFullYear()))

const form = ref({
  leave_type_id: '',
  start_date: '',
  end_date: '',
  start_portion: 'full',
  end_portion: 'full',
  reason: '',
  contact_while_away: '',
})
const attachment = ref(null)
const submitting = ref(false)
const error = ref(null)
const fieldErrors = ref({})

onMounted(() => {
  types.run().catch(() => {})
  balances.run().catch(() => {})
})

const selectedType = computed(
  () => (types.data.value ?? []).find((type) => type.id === form.value.leave_type_id) ?? null,
)

const selectedBalance = computed(() => {
  if (!selectedType.value) return null
  return (balances.data.value?.data ?? []).find((row) => row.code === selectedType.value.code) ?? null
})

const singleDay = computed(
  () => form.value.start_date && form.value.start_date === form.value.end_date,
)

/*
 * A calendar-day count, shown only as a rough guide. The authoritative working
 * day figure comes back from the server, which knows the rest days and the
 * public holiday calendar.
 */
const calendarDays = computed(() => {
  if (!form.value.start_date || !form.value.end_date) return null
  const start = new Date(form.value.start_date)
  const end = new Date(form.value.end_date)
  if (end < start) return null
  return Math.round((end - start) / 86400000) + 1
})

function onDatesChanged() {
  if (form.value.start_date && !form.value.end_date) {
    form.value.end_date = form.value.start_date
  }
  if (singleDay.value) {
    form.value.end_portion = form.value.start_portion
  }
}

async function submit() {
  error.value = null
  fieldErrors.value = {}
  submitting.value = true

  try {
    const payload = new FormData()
    Object.entries(form.value).forEach(([key, value]) => {
      if (value !== '' && value !== null) payload.append(key, value)
    })
    if (attachment.value) payload.append('attachment', attachment.value)

    await leaveApi.apply(payload)
    router.push({ name: 'leave' })
  } catch (caught) {
    error.value = readError(caught)
    fieldErrors.value = readFieldErrors(caught)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="stack" style="max-width: 46rem">
    <header>
      <RouterLink :to="{ name: 'leave' }" class="ref">← My leave</RouterLink>
      <h1 class="page-title" style="margin-top: var(--s2)">Apply for leave</h1>
      <p class="lede">
        Weekends and public holidays inside your dates are not charged against
        your balance. The exact working-day count is confirmed when you submit.
      </p>
    </header>

    <StateBlock :loading="types.loading.value" :error="types.error.value" @retry="types.run()">
      <form class="panel" novalidate @submit.prevent="submit">
        <div class="panel-body">
          <div v-if="error" class="notice notice-error" style="margin-bottom: var(--s5)">
            {{ error }}
          </div>

          <FormField id="leave_type_id" label="Leave type" :error="fieldErrors.leave_type_id">
            <template #default="{ id, invalid }">
              <select
                :id="id"
                v-model="form.leave_type_id"
                class="control"
                :aria-invalid="invalid"
                required
              >
                <option value="" disabled>Choose a leave type</option>
                <option v-for="type in types.data.value" :key="type.id" :value="type.id">
                  {{ type.name }}{{ type.is_paid ? '' : ' (unpaid)' }}
                </option>
              </select>
            </template>
          </FormField>

          <div v-if="selectedType" class="type-note">
            <p v-if="selectedBalance" class="balance-line">
              <span class="figure">{{ selectedBalance.available }}</span> days available
              <span v-if="selectedBalance.pending" class="ref">
                · {{ selectedBalance.pending }} already pending
              </span>
            </p>
            <ul class="rules">
              <li v-if="selectedType.min_notice_days">
                Needs {{ selectedType.min_notice_days }} days notice
              </li>
              <li v-if="selectedType.max_consecutive_days">
                Up to {{ selectedType.max_consecutive_days }} consecutive days per request
              </li>
              <li v-if="selectedType.requires_attachment">Supporting document required</li>
              <li v-if="!selectedType.allows_half_day">Whole days only</li>
              <li v-if="!selectedType.tracks_balance">No fixed entitlement</li>
            </ul>
          </div>

          <div class="field-row">
            <FormField id="start_date" label="First day" :error="fieldErrors.start_date">
              <template #default="{ id, invalid }">
                <input
                  :id="id"
                  v-model="form.start_date"
                  class="control control-mono"
                  type="date"
                  :aria-invalid="invalid"
                  required
                  @change="onDatesChanged"
                />
              </template>
            </FormField>

            <FormField id="end_date" label="Last day" :error="fieldErrors.end_date">
              <template #default="{ id, invalid }">
                <input
                  :id="id"
                  v-model="form.end_date"
                  class="control control-mono"
                  type="date"
                  :min="form.start_date"
                  :aria-invalid="invalid"
                  required
                  @change="onDatesChanged"
                />
              </template>
            </FormField>
          </div>

          <div v-if="selectedType?.allows_half_day" class="field-row">
            <FormField
              id="start_portion"
              :label="singleDay ? 'Part of the day' : 'First day starts'"
            >
              <template #default="{ id }">
                <select :id="id" v-model="form.start_portion" class="control" @change="onDatesChanged">
                  <option value="full">Full day</option>
                  <option value="first_half">Morning only</option>
                  <option value="second_half">Afternoon only</option>
                </select>
              </template>
            </FormField>

            <FormField v-if="!singleDay" id="end_portion" label="Last day ends">
              <template #default="{ id }">
                <select :id="id" v-model="form.end_portion" class="control">
                  <option value="full">Full day</option>
                  <option value="first_half">Morning only</option>
                  <option value="second_half">Afternoon only</option>
                </select>
              </template>
            </FormField>
          </div>

          <p v-if="calendarDays" class="ref span-note">
            {{ calendarDays }} calendar day{{ calendarDays === 1 ? '' : 's' }} selected · working
            days confirmed on submit
          </p>

          <FormField
            id="reason"
            label="Reason"
            hint="Your approver sees this. Keep it brief."
            :error="fieldErrors.reason"
          >
            <template #default="{ id, invalid }">
              <textarea
                :id="id"
                v-model="form.reason"
                class="control"
                rows="3"
                maxlength="500"
                :aria-invalid="invalid"
                required
              ></textarea>
            </template>
          </FormField>

          <FormField
            id="contact_while_away"
            label="Contact while away"
            hint="Optional — a number or a colleague covering for you."
            :error="fieldErrors.contact_while_away"
          >
            <template #default="{ id }">
              <input :id="id" v-model="form.contact_while_away" class="control" type="text" />
            </template>
          </FormField>

          <FormField
            v-if="selectedType?.requires_attachment"
            id="attachment"
            label="Supporting document"
            hint="PDF or image, up to 5 MB. Stored privately and seen only by your approver and HR."
            :error="fieldErrors.attachment"
          >
            <template #default="{ id }">
              <input
                :id="id"
                class="control"
                type="file"
                accept=".pdf,.jpg,.jpeg,.png"
                @change="attachment = $event.target.files[0] ?? null"
              />
            </template>
          </FormField>
        </div>

        <div class="actions">
          <RouterLink class="btn btn-quiet" :to="{ name: 'leave' }">Cancel</RouterLink>
          <button class="btn" type="submit" :disabled="submitting">
            {{ submitting ? 'Submitting…' : 'Submit request' }}
          </button>
        </div>
      </form>
    </StateBlock>
  </div>
</template>

<style scoped>
.type-note {
  padding: var(--s4);
  background: var(--teal-wash);
  border-radius: var(--radius);
  margin-bottom: var(--s5);
}

.balance-line .figure {
  font-size: var(--step-2);
  font-weight: 600;
  color: var(--teal);
}

.rules {
  margin: var(--s3) 0 0;
  padding-left: 1.1rem;
  font-size: var(--step--1);
  color: var(--ink-soft);
}

.span-note {
  margin: calc(-1 * var(--s2)) 0 var(--s5);
}

.actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--s3);
  padding: var(--s4) var(--s5);
  border-top: 1px solid var(--rule);
  background: var(--sunk);
}
</style>
