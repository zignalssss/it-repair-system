<script setup lang="ts">
import { computed } from 'vue'
import { routeOf, statusLabels } from '@/lib/ticketLabels'
import type { Ticket } from '@/services/tickets'

const props = defineProps<{
  ticket: Pick<Ticket, 'status' | 'support_level'>
  large?: boolean
  passed?: ReadonlySet<number>
}>()

const stops = computed(() => routeOf(props.ticket, props.passed))
const summary = computed(() => {
  const here = stops.value.find((stop) => stop.state !== 'done' && stop.state !== 'idle')
  return `เส้นทางใบงาน: ${here?.label ?? ''} · ${statusLabels[props.ticket.status]}`
})
</script>

<template>
  <div role="img" :aria-label="summary" class="strip" :class="{ 'strip-lg': large }">
    <div v-for="stop in stops" :key="stop.label" class="stop" :data-state="stop.state">
      <span class="dot" aria-hidden="true"></span>
      <span v-if="large" class="name">{{ stop.label }}</span>
    </div>
  </div>
</template>

<style scoped>
.strip {
  --gap: 1.25rem;
  --size: 0.75rem;
  position: relative;
  display: grid;
  width: max-content;
  grid-template-columns: repeat(5, var(--size));
  column-gap: var(--gap);
}
.strip::before {
  content: '';
  position: absolute;
  top: calc(var(--size) / 2 - 1px);
  left: calc(var(--size) / 2);
  right: calc(var(--size) / 2);
  height: 2px;
  background: var(--color-ink-200);
}
.strip-lg {
  --gap: 0;
  --size: 1.125rem;
  width: 100%;
  grid-template-columns: repeat(5, minmax(0, 1fr));
}
.strip-lg::before {
  left: 10%;
  right: 10%;
}
.stop {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
}
.dot {
  position: relative;
  box-sizing: border-box;
  width: var(--size);
  height: var(--size);
  border: 2px solid var(--color-ink-300);
  border-radius: 999px;
  background: #fff;
}
.name {
  font-size: 0.75rem;
  line-height: 1.3;
  color: var(--color-ink-500);
  text-align: center;
}
[data-state='done'] .dot {
  border-color: var(--color-ink-900);
  background: var(--color-ink-900);
}
[data-state='active'] .dot {
  border-color: var(--color-ink-900);
  background: var(--color-ink-900);
  box-shadow:
    0 0 0 3px #fff,
    0 0 0 5px var(--color-ink-900);
}
[data-state='queued'] .dot {
  border-color: var(--color-ink-900);
  box-shadow:
    0 0 0 3px #fff,
    0 0 0 4px var(--color-ink-300);
}
[data-state='waiting'] .dot {
  border-color: var(--color-kraft-500);
  background: var(--color-kraft-500);
}
[data-state='closed'] .dot {
  border-color: var(--color-scan-600);
  background: var(--color-scan-600);
}
[data-state='void'] .dot::after {
  content: '';
  position: absolute;
  inset: 50% -3px auto;
  height: 2px;
  background: var(--color-ink-500);
  transform: rotate(-40deg);
}
@media (max-width: 639px) {
  [data-state='done'] .name,
  [data-state='idle'] .name {
    visibility: hidden;
  }
}
[data-state='done'] .name,
[data-state='active'] .name,
[data-state='queued'] .name,
[data-state='waiting'] .name,
[data-state='closed'] .name {
  color: var(--color-ink-900);
}
[data-state='active'] .name,
[data-state='queued'] .name,
[data-state='waiting'] .name,
[data-state='closed'] .name,
[data-state='void'] .name {
  font-weight: 600;
}
</style>
