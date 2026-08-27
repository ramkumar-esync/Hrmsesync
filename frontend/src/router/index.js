import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/sign-in',
    name: 'sign-in',
    component: () => import('@/views/SignInView.vue'),
    meta: { public: true, title: 'Sign in' },
  },
  {
    path: '/',
    name: 'overview',
    component: () => import('@/views/OverviewView.vue'),
    meta: { title: 'Overview' },
  },
  {
    path: '/account',
    name: 'account',
    component: () => import('@/views/AccountView.vue'),
    meta: { title: 'My account' },
  },
  {
    path: '/payslips',
    name: 'payslips',
    component: () => import('@/views/PayslipsView.vue'),
    meta: { title: 'My payslips' },
  },
  {
    path: '/payslips/:id',
    name: 'payslip',
    component: () => import('@/views/PayslipView.vue'),
    props: true,
    meta: { title: 'Payslip' },
  },
  {
    path: '/leave',
    name: 'leave',
    component: () => import('@/views/LeaveView.vue'),
    meta: { title: 'My leave' },
  },
  {
    path: '/leave/apply',
    name: 'leave-apply',
    component: () => import('@/views/LeaveApplyView.vue'),
    meta: { title: 'Apply for leave' },
  },
  {
    path: '/attendance',
    name: 'attendance',
    component: () => import('@/views/AttendanceView.vue'),
    meta: { title: 'My attendance' },
  },
  {
    path: '/approvals',
    name: 'approvals',
    component: () => import('@/views/ApprovalsView.vue'),
    meta: { roles: ['manager', 'hr_admin'], title: 'Approvals' },
  },
  {
    path: '/hr/employees',
    name: 'hr-employees',
    component: () => import('@/views/hr/EmployeesView.vue'),
    meta: { roles: ['hr_admin'], title: 'Employees' },
  },
  {
    path: '/hr/attendance',
    name: 'hr-attendance',
    component: () => import('@/views/hr/AttendanceReviewView.vue'),
    meta: { roles: ['hr_admin'], title: 'Attendance review' },
  },
  {
    path: '/hr/payroll',
    name: 'hr-payroll',
    component: () => import('@/views/hr/PayrollRunsView.vue'),
    meta: { roles: ['hr_admin'], title: 'Payroll runs' },
  },
  {
    path: '/hr/payroll/:id',
    name: 'hr-payroll-run',
    component: () => import('@/views/hr/PayrollRunView.vue'),
    props: true,
    meta: { roles: ['hr_admin'], title: 'Payroll run' },
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/NotFoundView.vue'),
    meta: { title: 'Not found' },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: (to, from, saved) => saved ?? { top: 0 },
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.public) {
    return auth.isAuthenticated ? { name: 'overview' } : true
  }

  if (!auth.isAuthenticated) {
    return { name: 'sign-in', query: { next: to.fullPath } }
  }

  // On a hard refresh the token survives but the user object does not.
  if (!auth.user) {
    await auth.fetchUser()
    if (!auth.isAuthenticated) return { name: 'sign-in', query: { next: to.fullPath } }
  }

  if (to.meta.roles && !to.meta.roles.includes(auth.role)) {
    return { name: 'overview' }
  }

  return true
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} · Payroll Portal` : 'Payroll Portal'
})

export default router
