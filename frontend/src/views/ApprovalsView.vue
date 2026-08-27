<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAsync } from '@/composables/useAsync'
import { approvals as approvalApi } from '@/api/resources'
import { readError } from '@/api/client'
import { dateRange, dateTime } from '@/format'
import StateBlock from '@/components/StateBlock.vue'

const state = useAsync(() => approvalApi.pending(), [])
const deciding = ref(null)
const rejecting = ref(null)
const note = ref('')
const error = ref(null)
const done = ref(null)

onMounted(() => state.run().catch(() => {}))

const rows = computed(() => state.data.value ?? [])

async function decide(application, approve) {
  if (!approve && rejecting.value !== application.id) {
    rejecting.value = application.id
    note.value = ''
    return
  }

  if (!approve && note.value.trim().length < 3) {
    error.value = 'Give a reason so the employee knows why this was declined.'
    return
  }

  error.value = null
  deciding.value = application.id

  try {
    await approvalApi.decide(application.id, {
      decision: approve ? 'approve' : 'reject',
      note: approve ? undefined : note.value,
    })
    done.value = `${application.employee_name}'s request was ${approve ? 'approved' : 'declined'}.`
    rejecting.value = null
    note.value = ''
    await state.run()
  } catch (caught) {
    error.value = readError(caught)
  } finally {
    deciding.value = null
  }
}
</script>

<template>
  <div class="stack">
    <header>
      <p class="eyebrow">Approvals</p>
      <h1 class="page-title">Leave awaiting your decision</h1>
      <p class="lede">
        Approving moves the days from pending to taken. Declining returns them to
        the employee's balance straight away.
      </p>
    </header>

    <div v-if="done" class="notice">{{ done }}</div>
    <div v-if="error" class="notice notice-error">{{ error }}</div>

    <section class="panel">
      <StateBlock
        :loading="state.loading.value"
        :error="state.error.value"
        :empty="rows.length === 0"
        empty-title="Nothing to decide"
        empty-body="Requests from your team appear here as soon as they are submitted."
        @retry="state.run()"
      >
        <ul class="requests">
          <li v-for="row in rows" :key="row.id" class="request">
            <div class="request-main">
              <div class="request-head">
                <span class="who">{{ row.employee_name }}</span>
                <span class="ref">{{ row.employee_number }}</span>
              </div>
              <p class="what">
                <strong>{{ row.leave_type_name }}</strong>
                <span v-if="!row.is_paid" class="tag tag-neutral">Unpaid</span>
                · {{ dateRange(row.start_date, row.end_date) }}
                · <span class="figure">{{ row.working_days }}</span> working days
              </p>
              <p class="why">{{ row.reason }}</p>
              <p class="ref">
                Applied {{ dateTime(row.applied_at) }}
                <span v-if="row.attachment_path"> · document attached</span>
              </p>

              <div v-if="rejecting === row.id" class="reject-box">
                <label class="field-label" :for="`note-${row.id}`">
                  Why is this being declined?
                </label>
                <textarea
                  :id="`note-${row.id}`"
                  v-model="note"
                  class="control"
                  rows="2"
                  placeholder="The employee will see this."
                ></textarea>
              </div>
            </div>

            <div class="request-actions">
              <button
                class="btn btn-quiet btn-small"
                :disabled="deciding === row.id"
                @click="decide(row, false)"
              >
                {{ rejecting === row.id ? 'Confirm decline' : 'Decline' }}
              </button>
              <button class="btn btn-small" :disabled="deciding === row.id" @click="decide(row, true)">
                {{ deciding === row.id ? 'Saving…' : 'Approve' }}
              </button>
            </div>
          </li>
        </ul>
      </StateBlock>
    </section>
  </div>
</template>

<style scoped>
.requests {
  list-style: none;
  margin: 0;
  padding: 0;
}

.request {
  display: flex;
  gap: var(--s5);
  align-items: flex-start;
  justify-content: space-between;
  padding: var(--s5);
  border-bottom: 1px solid var(--rule);
  flex-wrap: wrap;
}

.request:last-child {
  border-bottom: 0;
}

.request-main {
  flex: 1 1 22rem;
}

.request-head {
  display: flex;
  align-items: baseline;
  gap: var(--s3);
}

.who {
  font-weight: 600;
}

.what {
  margin-top: var(--s2);
  color: var(--ink-soft);
}

.why {
  margin-top: var(--s2);
  padding-left: var(--s3);
  border-left: 2px solid var(--rule-strong);
  color: var(--ink-soft);
}

.reject-box {
  margin-top: var(--s4);
  padding: var(--s4);
  background: var(--red-wash);
  border-radius: var(--radius);
}

.request-actions {
  display: flex;
  gap: var(--s2);
  flex: 0 0 auto;
}
</style>
