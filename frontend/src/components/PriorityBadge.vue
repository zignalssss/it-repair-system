<script setup lang="ts">
import { priorityLabels } from '@/lib/ticketLabels'
import type { Ticket } from '@/services/tickets'

defineProps<{ priority: Ticket['priority'] }>()
</script>

<template>
  <span
    class="inline-flex rounded-[3px] px-1.5 py-0.5 text-xs font-semibold"
    :class="{
      'bg-vermilion-600 text-white': priority === 'critical',
      'text-vermilion-700 ring-1 ring-vermilion-600 ring-inset': priority === 'high',
      'text-ink-600 ring-1 ring-ink-200 ring-inset': priority === 'normal',
    }"
  >
    {{ priorityLabels[priority] ?? priority }}
  </span>
</template>
