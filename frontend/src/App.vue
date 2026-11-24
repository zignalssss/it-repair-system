<script setup lang="ts">
import { RouterView, useRoute } from 'vue-router'
import AppShell from '@/components/AppShell.vue'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const auth = useAuthStore()
</script>

<template>
  <AppShell v-if="route.meta.requiresAuth && auth.user">
    <RouterView />
  </AppShell>
  <RouterView v-else />
</template>
