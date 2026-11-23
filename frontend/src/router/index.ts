import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
    },
    {
      path: '/',
      name: 'home',
      component: () => import('@/views/HomeView.vue'),
      meta: {
        requiresAuth: true,
      },
    },
    {
      path: '/tickets/new',
      name: 'ticket-create',
      component: () => import('@/views/TicketCreateView.vue'),
      meta: {
        requiresAuth: true,
      },
    },
    {
      path: '/tickets/:id(\\d+)',
      name: 'ticket-detail',
      component: () => import('@/views/TicketDetailView.vue'),
      meta: {
        requiresAuth: true,
      },
    },
    {
      path: '/reports',
      name: 'manager-reports',
      component: () => import('@/views/ManagerReportsView.vue'),
      meta: { requiresAuth: true, requiresManager: true },
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: '/',
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  try {
    await auth.fetchUser()
  } catch {
    if (to.name === 'login') {
      return true
    }

    return {
      name: 'login',
      query: {
        unavailable: '1',
      },
    }
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.meta.requiresManager && (auth.user?.role !== 'it_manager' || !auth.user.is_active)) {
    return { name: 'home' }
  }

  if (to.name === 'login' && auth.isAuthenticated) {
    return { name: 'home' }
  }

  return true
})

export default router