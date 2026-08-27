import { defineStore } from 'pinia'
import { auth as authApi } from '@/api/resources'

const TOKEN_KEY = 'payroll_token'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem(TOKEN_KEY),
    user: null,
    loading: false,
  }),

  getters: {
    isAuthenticated: (state) => Boolean(state.token),
    role: (state) => state.user?.role ?? null,
    isHr: (state) => state.user?.role === 'hr_admin',
    isApprover: (state) => ['manager', 'hr_admin'].includes(state.user?.role),
    // An account with no employee record cannot have payslips or leave.
    hasEmployeeRecord: (state) => Boolean(state.user?.employee_id),
    initials: (state) =>
      (state.user?.name ?? '?')
        .split(' ')
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase(),
  },

  actions: {
    async login(credentials) {
      this.loading = true
      try {
        const { token, user } = await authApi.login({ ...credentials, device_name: 'web' })
        this.token = token
        this.user = user.data ?? user
        localStorage.setItem(TOKEN_KEY, token)
        return this.user
      } finally {
        this.loading = false
      }
    },

    /** Called on boot and after a hard refresh to rehydrate the session. */
    async fetchUser() {
      if (!this.token) return null
      try {
        this.user = await authApi.me()
        return this.user
      } catch {
        this.clear()
        return null
      }
    },

    async logout() {
      try {
        await authApi.logout()
      } catch {
        // The token may already be gone server-side; sign out locally regardless.
      }
      this.clear()
    },

    clear() {
      this.token = null
      this.user = null
      localStorage.removeItem(TOKEN_KEY)
    },
  },
})
