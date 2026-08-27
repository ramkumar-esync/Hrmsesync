<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import BrandMark from '@/components/BrandMark.vue'
import AppIcon from '@/components/AppIcon.vue'
import NotificationBell from '@/components/NotificationBell.vue'

const auth = useAuthStore()
const router = useRouter()
const drawerOpen = ref(false)

/*
 * Navigation is built from the person's role, so a manager never sees an HR
 * link they would only be refused at. Each item names what the person manages,
 * not the endpoint behind it, and carries its own icon.
 */
const links = computed(() => {
  const items = [{ to: { name: 'overview' }, label: 'Overview', icon: 'overview' }]

  if (auth.hasEmployeeRecord) {
    items.push(
      { to: { name: 'payslips' }, label: 'Payslips', icon: 'payslip' },
      { to: { name: 'leave' }, label: 'Leave', icon: 'leave' },
      { to: { name: 'attendance' }, label: 'Attendance', icon: 'clock' },
    )
  }

  if (auth.isApprover) {
    items.push({ to: { name: 'approvals' }, label: 'Approvals', icon: 'approvals' })
  }

  if (auth.isHr) {
    items.push(
      { to: { name: 'hr-payroll' }, label: 'Payroll', icon: 'payroll' },
      { to: { name: 'hr-attendance' }, label: 'Attendance review', icon: 'calendarCheck' },
      { to: { name: 'hr-employees' }, label: 'Employees', icon: 'employees' },
    )
  }

  return items
})

async function signOut() {
  await auth.logout()
  router.push({ name: 'sign-in' })
}
</script>

<template>
  <div class="shell">
    <!-- Sidebar -->
    <aside class="sidebar" :class="{ 'is-open': drawerOpen }">
      <div class="sidebar-brand">
        <BrandMark :size="18" />
        <span class="brand-name">Payroll Portal</span>
        <NotificationBell class="brand-bell" />
      </div>

      <nav class="nav" aria-label="Main">
        <RouterLink
          v-for="link in links"
          :key="link.label"
          :to="link.to"
          class="nav-link"
          @click="drawerOpen = false"
        >
          <AppIcon :name="link.icon" :size="18" />
          <span>{{ link.label }}</span>
        </RouterLink>
      </nav>

      <div class="sidebar-foot">
        <router-link :to="{ name: 'account' }" class="who" @click="drawerOpen = false">
          <span class="avatar" aria-hidden="true">{{ auth.initials }}</span>
          <span class="who-detail">
            <span class="who-name">{{ auth.user?.name }}</span>
            <span class="who-role">{{ auth.user?.role_label }}</span>
          </span>
        </router-link>
        <button class="signout" @click="signOut">
          <AppIcon name="logout" :size="16" />
          <span>Sign out</span>
        </button>
      </div>
    </aside>

    <!-- Scrim behind the mobile drawer -->
    <div v-if="drawerOpen" class="scrim" @click="drawerOpen = false"></div>

    <!-- Main column -->
    <div class="main">
      <header class="topbar">
        <button class="drawer-btn" aria-label="Open navigation" @click="drawerOpen = true">
          <AppIcon name="menu" :size="22" />
        </button>
        <BrandMark :size="16" class="topbar-brand" />
        <span class="topbar-avatar" aria-hidden="true">{{ auth.initials }}</span>
      </header>

      <main class="content">
        <slot />
      </main>

      <footer class="foot">
        <span class="ref">Payroll Portal</span>
        <span class="ref">Figures shown are as issued. Contact HR to query a payslip.</span>
      </footer>
    </div>
  </div>
</template>

<style scoped>
.shell {
  min-height: 100vh;
  display: flex;
}

/* ---------------------------------------------------------------- Sidebar */
.sidebar {
  width: var(--sidebar-w);
  flex: 0 0 var(--sidebar-w);
  background: var(--surface);
  border-right: 1px solid var(--rule);
  display: flex;
  flex-direction: column;
  position: sticky;
  top: 0;
  height: 100vh;
}

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: var(--s5);
  border-bottom: 1px solid var(--rule);
}

.brand-bell {
  margin-left: auto;
}

.brand-name {
  font-weight: 600;
  letter-spacing: -0.015em;
}

.nav {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: var(--s4) var(--s3);
  flex: 1;
  overflow-y: auto;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: var(--s3);
  padding: 0.6rem 0.75rem;
  border-radius: var(--radius-sm);
  color: var(--ink-soft);
  text-decoration: none;
  font-size: var(--step-0);
  font-weight: 500;
  transition: background 120ms ease, color 120ms ease;
}

.nav-link:hover {
  background: var(--sunk);
  color: var(--ink);
}

.nav-link.router-link-active {
  background: var(--teal-wash);
  color: var(--teal);
}

.nav-link.router-link-active :deep(.icon) {
  color: var(--teal);
}

.sidebar-foot {
  border-top: 1px solid var(--rule);
  padding: var(--s4);
}

.who {
  display: flex;
  align-items: center;
  gap: var(--s3);
  margin-bottom: var(--s3);
  text-decoration: none;
  color: inherit;
  padding: var(--s2);
  margin-left: calc(var(--s2) * -1);
  border-radius: var(--radius-sm);
  transition: background 120ms ease;
}

.who:hover {
  background: var(--sidebar-hover, rgba(255, 255, 255, 0.06));
}

.avatar {
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: var(--teal-wash);
  color: var(--teal);
  font-family: var(--mono);
  font-size: 0.75rem;
  font-weight: 600;
}

.who-detail {
  display: flex;
  flex-direction: column;
  line-height: 1.25;
  min-width: 0;
}

.who-name {
  font-size: var(--step--1);
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.who-role {
  font-family: var(--mono);
  font-size: 0.625rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--muted);
}

.signout {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--s2);
  padding: 0.45rem;
  border: 1px solid var(--rule-strong);
  border-radius: var(--radius-sm);
  background: var(--surface);
  color: var(--ink-soft);
  font-size: var(--step--1);
  cursor: pointer;
  transition: background 120ms ease, border-color 120ms ease;
}

.signout:hover {
  background: var(--sunk);
  border-color: var(--muted);
}

/* ------------------------------------------------------------------- Main */
.main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

/* The topbar only appears on mobile, where the sidebar becomes a drawer. */
.topbar {
  display: none;
  align-items: center;
  gap: var(--s4);
  padding: 0 var(--s4);
  min-height: 56px;
  background: var(--surface);
  border-bottom: 1px solid var(--rule);
  position: sticky;
  top: 0;
  z-index: 20;
}

.drawer-btn {
  background: none;
  border: none;
  padding: var(--s2);
  cursor: pointer;
  color: var(--ink);
  display: inline-flex;
}

.topbar-brand {
  margin-right: auto;
}

.topbar-avatar {
  width: 32px;
  height: 32px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: var(--teal-wash);
  color: var(--teal);
  font-family: var(--mono);
  font-size: 0.7rem;
  font-weight: 600;
}

.content {
  flex: 1;
  width: 100%;
  max-width: 1120px;
  margin: 0 auto;
  padding: var(--s6) var(--s6) var(--s7);
}

.foot {
  border-top: 1px solid var(--rule);
  padding: var(--s4) var(--s6);
  display: flex;
  justify-content: space-between;
  gap: var(--s4);
  flex-wrap: wrap;
}

.scrim {
  display: none;
}

/* ----------------------------------------------------------------- Mobile */
@media (max-width: 860px) {
  .topbar {
    display: flex;
  }

  .sidebar {
    position: fixed;
    left: 0;
    top: 0;
    z-index: 40;
    transform: translateX(-100%);
    transition: transform 220ms ease;
    box-shadow: var(--shadow-lg);
  }

  .sidebar.is-open {
    transform: translateX(0);
  }

  .scrim {
    display: block;
    position: fixed;
    inset: 0;
    background: rgba(23, 26, 31, 0.4);
    z-index: 30;
  }

  .content {
    padding: var(--s5) var(--s4) var(--s6);
  }

  .foot {
    padding: var(--s4);
  }
}
</style>
