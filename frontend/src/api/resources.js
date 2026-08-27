import client from './client'

/*
 * A thin, named layer over the endpoints. Views ask for what they want in
 * domain language and never build URLs themselves, so a route change is a
 * one-line edit here.
 */

export const auth = {
  login: (payload) => client.post('/auth/login', payload).then((r) => r.data),
  logout: () => client.post('/auth/logout'),
  me: () => client.get('/auth/me').then((r) => r.data.data ?? r.data),
  changePassword: (payload) => client.post('/auth/change-password', payload).then((r) => r.data),
}

export const payslips = {
  mine: (year) => client.get('/me/payslips', { params: { year } }).then((r) => r.data),
  show: (id) => client.get(`/payslips/${id}`).then((r) => r.data.data ?? r.data),
  downloadUrl: (id) => `${import.meta.env.VITE_API_BASE_URL || ''}/api/payslips/${id}/download`,
  download: (id) => client.get(`/payslips/${id}/download`, { responseType: 'blob' }),
}

export const leave = {
  types: () => client.get('/leave/types').then((r) => r.data.data),
  balances: (year) => client.get('/me/leave/balances', { params: { year } }).then((r) => r.data),
  applications: (params) => client.get('/me/leave/applications', { params }).then((r) => r.data),
  show: (id) => client.get(`/leave/applications/${id}`).then((r) => r.data.data ?? r.data),
  apply: (formData) =>
    client
      .post('/leave/applications', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      .then((r) => r.data.data ?? r.data),
  cancel: (id) => client.post(`/leave/applications/${id}/cancel`).then((r) => r.data),
}

export const approvals = {
  pending: () => client.get('/approvals/leave').then((r) => r.data.data),
  decide: (id, payload) => client.post(`/approvals/leave/${id}`, payload).then((r) => r.data),
  calendar: (from, to) =>
    client.get('/approvals/leave/calendar', { params: { from, to } }).then((r) => r.data.data),
}

export const birthdays = {
  upcoming: (days = 30) =>
    client.get('/birthdays', { params: { days } }).then((r) => r.data.data),
}

export const attendance = {
  mine: (period) =>
    client.get('/me/attendance', { params: { period } }).then((r) => r.data.data ?? r.data),
  save: (payload) => client.post('/me/attendance', payload).then((r) => r.data.data ?? r.data),
  submit: (period) =>
    client.post('/me/attendance/submit', { period }).then((r) => r.data.data ?? r.data),
  // HR review
  pending: () => client.get('/hr/attendance').then((r) => r.data.data),
  show: (id) => client.get(`/hr/attendance/${id}`).then((r) => r.data),
  decide: (id, payload) =>
    client.post(`/hr/attendance/${id}/decide`, payload).then((r) => r.data.data ?? r.data),
}

export const employees = {
  list: (params) => client.get('/hr/employees', { params }).then((r) => r.data),
  show: (id) => client.get(`/hr/employees/${id}`).then((r) => r.data.data ?? r.data),
  create: (payload) => client.post('/hr/employees', payload).then((r) => r.data),
  updateProfile: (id, payload) =>
     client.put(`/hr/employees/${id}/profile`, payload).then((r) => r.data.data ?? r.data),
  updateCompensation: (id, payload) =>
    client.put(`/hr/employees/${id}/compensation`, payload).then((r) => r.data),
  changeStatus: (id, payload) =>
    client.put(`/hr/employees/${id}/status`, payload).then((r) => r.data.data ?? r.data),
  resetPassword: (id) => client.post(`/employees/${id}/reset-password`).then((r) => r.data),
  terminate: (id, payload) => client.post(`/hr/employees/${id}/terminate`, payload),
  leaveBalances: (id, year) =>
    client.get(`/hr/employees/${id}/leave-balances`, { params: { year } }).then((r) => r.data),
}

export const holidays = {
  list: (year) => client.get('/holidays', { params: { year } }).then((r) => r.data.data),
}

export const payrollRuns = {
  list: (params) => client.get('/hr/payroll-runs', { params }).then((r) => r.data),
  show: (id) => client.get(`/hr/payroll-runs/${id}`).then((r) => r.data.data ?? r.data),
  open: (payload) => client.post('/hr/payroll-runs', payload).then((r) => r.data.data ?? r.data),
  populate: (id) => client.post(`/hr/payroll-runs/${id}/populate`).then((r) => r.data),
  payslips: (id) => client.get(`/hr/payroll-runs/${id}/payslips`).then((r) => r.data.data),
  recordEntry: (id, payload) =>
    client.post(`/hr/payroll-runs/${id}/entries`, payload).then((r) => r.data.data ?? r.data),
  removePayslip: (runId, payslipId) =>
    client.delete(`/hr/payroll-runs/${runId}/payslips/${payslipId}`),
  finalise: (id) => client.post(`/hr/payroll-runs/${id}/finalise`).then((r) => r.data.data ?? r.data),
  markPaid: (id, payload) =>
    client.post(`/hr/payroll-runs/${id}/mark-paid`, payload).then((r) => r.data.data ?? r.data),
}

export const entitlements = {
  grant: (payload) => client.post('/hr/leave/entitlements/grant', payload).then((r) => r.data),
  adjust: (payload) => client.post('/hr/leave/entitlements/adjust', payload).then((r) => r.data),
}
