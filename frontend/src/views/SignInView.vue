<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { readError, readFieldErrors } from '@/api/client'
import FormField from '@/components/FormField.vue'
import BrandMark from '@/components/BrandMark.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const form = ref({ email: '', password: '' })
const error = ref(null)
const fieldErrors = ref({})

async function submit() {
  error.value = null
  fieldErrors.value = {}

  try {
    await auth.login(form.value)
    router.push(route.query.next || { name: 'overview' })
  } catch (caught) {
    error.value = readError(caught)
    fieldErrors.value = readFieldErrors(caught)
  }
}
</script>

<template>
  <div class="split">
    <aside class="pitch">
      <div class="pitch-inner">
        <BrandMark :size="54" light class="brand-logo" />
        <h1 class="pitch-title">Payroll Portal</h1>
        <p class="pitch-copy">
          Your payslips, your leave balance, and where every request has got to —
          in one place.
        </p>
        <dl class="pitch-list">
          <div><dt>Payslips</dt><dd>Every period, downloadable as a PDF</dd></div>
          <div><dt>Leave</dt><dd>Apply, and see what you have left</dd></div>
          <div><dt>Approvals</dt><dd>Decide on your team's requests</dd></div>
        </dl>
      </div>
    </aside>

    <main class="form-side">
      <form class="form-card" novalidate @submit.prevent="submit">
        <p class="eyebrow">Sign in</p>
        <h2 class="form-title">Welcome back</h2>
        <p class="form-sub">Use the work email address HR holds for you.</p>

        <div v-if="error" class="notice notice-error" style="margin-bottom: var(--s4)">
          {{ error }}
        </div>

        <FormField id="email" label="Work email" :error="fieldErrors.email">
          <template #default="{ id, invalid }">
            <input
              :id="id"
              v-model="form.email"
              class="control"
              type="email"
              autocomplete="username"
              :aria-invalid="invalid"
              required
            />
          </template>
        </FormField>

        <FormField id="password" label="Password" :error="fieldErrors.password">
          <template #default="{ id, invalid }">
            <input
              :id="id"
              v-model="form.password"
              class="control"
              type="password"
              autocomplete="current-password"
              :aria-invalid="invalid"
              required
            />
          </template>
        </FormField>

        <button class="btn btn-block" type="submit" :disabled="auth.loading">
          {{ auth.loading ? 'Signing in…' : 'Sign in' }}
        </button>

        <p class="assist">
          Locked out or never received a password? Contact your HR administrator.
        </p>
      </form>
    </main>
  </div>
</template>

<style scoped>
.split {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 1.05fr 1fr;
  background: var(--surface);
}

.pitch {
  background: linear-gradient(150deg, var(--teal) 0%, var(--teal-deep) 100%);
  color: #fff;
  display: flex;
  align-items: center;
  padding: var(--s7) var(--s6);
  position: relative;
  overflow: hidden;
}

/* Faint ledger rules watermarked behind the pitch, echoing the pay band. */
.pitch::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: repeating-linear-gradient(
    180deg, transparent, transparent 26px,
    rgba(255, 255, 255, 0.04) 26px, rgba(255, 255, 255, 0.04) 27px);
  pointer-events: none;
}

.pitch-inner {
  position: relative;
}

.pitch-inner {
  max-width: 26rem;
  margin-left: auto;
  margin-right: var(--s6);
}

.brand-logo {
  margin-bottom: var(--s5);
}

.pitch-title {
  font-size: var(--step-4);
  font-weight: 600;
  letter-spacing: -0.03em;
  line-height: 1.05;
}

.pitch-copy {
  margin-top: var(--s4);
  color: #cfe0e1;
  font-size: var(--step-1);
  max-width: 30ch;
}

.pitch-list {
  margin-top: var(--s6);
  border-top: 1px solid rgba(255, 255, 255, 0.2);
}

.pitch-list > div {
  display: flex;
  gap: var(--s4);
  padding: var(--s3) 0;
  border-bottom: 1px solid rgba(255, 255, 255, 0.12);
}

.pitch-list dt {
  flex: 0 0 7rem;
  font-family: var(--mono);
  font-size: 0.6875rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--teal-line);
  padding-top: 0.2rem;
}

.pitch-list dd {
  margin: 0;
  font-size: var(--step--1);
  color: #e6efef;
}

.form-side {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  padding: var(--s7) var(--s6);
}

.form-card {
  width: 100%;
  max-width: 22rem;
}

.form-title {
  font-size: var(--step-2);
  font-weight: 600;
  letter-spacing: -0.02em;
  margin-top: var(--s2);
}

.form-sub {
  color: var(--muted);
  font-size: var(--step--1);
  margin-bottom: var(--s5);
}

.assist {
  margin-top: var(--s5);
  padding-top: var(--s4);
  border-top: 1px solid var(--rule);
  font-size: var(--step--1);
  color: var(--muted);
}

@media (max-width: 860px) {
  .split {
    grid-template-columns: 1fr;
  }

  .pitch {
    padding: var(--s6) var(--s5);
  }

  .pitch-inner {
    margin: 0;
    max-width: none;
  }

  .pitch-list {
    display: none;
  }

  .form-side {
    padding: var(--s6) var(--s5) var(--s7);
    justify-content: center;
  }
}
</style>
