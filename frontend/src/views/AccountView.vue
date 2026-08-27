<script setup>
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { auth as authApi } from '@/api/resources'
import { readError, readFieldErrors } from '@/api/client'
import FormField from '@/components/FormField.vue'

/*
 * The signed-in user's own account. For now this is where they change their
 * password — the first thing someone should do after signing in with the
 * temporary one from their welcome email.
 */
const auth = useAuthStore()

const form = ref({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
})
const saving = ref(false)
const error = ref(null)
const fieldErrors = ref({})
const done = ref(false)

async function submit() {
  error.value = null
  fieldErrors.value = {}
  done.value = false
  saving.value = true

  try {
    await authApi.changePassword(form.value)
    done.value = true
    form.value = { current_password: '', new_password: '', new_password_confirmation: '' }
  } catch (caught) {
    error.value = readError(caught)
    fieldErrors.value = readFieldErrors(caught)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="stack">
    <header>
      <p class="eyebrow">Account</p>
      <h1 class="page-title">My account</h1>
      <p class="lede">{{ auth.user?.name }} · {{ auth.user?.email }}</p>
    </header>

    <section class="panel" style="max-width: 34rem">
      <div class="panel-head"><h2>Change password</h2></div>
      <form class="panel-body" novalidate @submit.prevent="submit">
        <div v-if="done" class="notice" style="margin-bottom: var(--s5)">
          Your password has been changed. Other devices have been signed out.
        </div>
        <div v-if="error" class="notice notice-error" style="margin-bottom: var(--s5)">
          {{ error }}
        </div>

        <FormField id="current" label="Current password" :error="fieldErrors.current_password">
          <template #default="{ id }">
            <input
              :id="id"
              v-model="form.current_password"
              class="control"
              type="password"
              autocomplete="current-password"
              required
            />
          </template>
        </FormField>

        <FormField
          id="new"
          label="New password"
          hint="At least 8 characters, with letters and numbers."
          :error="fieldErrors.new_password"
        >
          <template #default="{ id }">
            <input
              :id="id"
              v-model="form.new_password"
              class="control"
              type="password"
              autocomplete="new-password"
              required
            />
          </template>
        </FormField>

        <FormField id="confirm" label="Confirm new password">
          <template #default="{ id }">
            <input
              :id="id"
              v-model="form.new_password_confirmation"
              class="control"
              type="password"
              autocomplete="new-password"
              required
            />
          </template>
        </FormField>

        <div style="display: flex; justify-content: flex-end">
          <button class="btn" type="submit" :disabled="saving">
            {{ saving ? 'Saving…' : 'Change password' }}
          </button>
        </div>
      </form>
    </section>
  </div>
</template>