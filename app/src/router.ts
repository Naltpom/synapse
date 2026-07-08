import { createRouter, createWebHistory } from 'vue-router'
import { ensureSession } from './lib/session'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: () => import('./views/LoginView.vue') },
    {
      path: '/',
      component: () => import('./components/AppShell.vue'),
      children: [
        { path: '', redirect: '/dashboard' },
        { path: 'dashboard', name: 'dashboard', component: () => import('./views/DashboardView.vue'), meta: { title: 'Vue d\'ensemble' } },
        { path: 'crm', name: 'crm', component: () => import('./views/CrmView.vue'), meta: { title: 'CRM' } },
        { path: 'staffing', name: 'staffing', component: () => import('./views/StaffingView.vue'), meta: { title: 'Staffing' } },
        { path: 'projets', name: 'projects', component: () => import('./views/ProjectsView.vue'), meta: { title: 'Projets' } },
        { path: 'facturation', name: 'billing', component: () => import('./views/BillingView.vue'), meta: { title: 'Facturation' } },
        { path: 'conges', name: 'leave', component: () => import('./views/LeaveView.vue'), meta: { title: 'Congés & validations' } },
        { path: 'audit', name: 'audit', component: () => import('./views/AuditView.vue'), meta: { title: 'Journal d\'audit' } },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const user = await ensureSession()
  if (!user && to.name !== 'login') return { name: 'login' }
  if (user && to.name === 'login') return { name: 'dashboard' }
  return true
})

export default router
