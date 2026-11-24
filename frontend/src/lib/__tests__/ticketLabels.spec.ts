import { describe, expect, it } from 'vitest'
import { passedStops, routeOf } from '@/lib/ticketLabels'

const states = (...args: Parameters<typeof routeOf>) => routeOf(...args).map((stop) => stop.state)

describe('routeOf', () => {
  it('places each status on its stop', () => {
    expect(states({ status: 'new', support_level: 'junior' })).toEqual([
      'done',
      'queued',
      'idle',
      'idle',
      'idle',
    ])
    expect(states({ status: 'in_progress', support_level: 'senior' })).toEqual([
      'done',
      'done',
      'active',
      'idle',
      'idle',
    ])
    expect(states({ status: 'waiting_equipment_approval', support_level: 'senior' })).toEqual([
      'done',
      'done',
      'done',
      'waiting',
      'idle',
    ])
    expect(states({ status: 'cancelled', support_level: 'junior' })).toEqual([
      'done',
      'void',
      'idle',
      'idle',
      'idle',
    ])
  })

  it('marks only stops the history proves on a closed ticket', () => {
    expect(states({ status: 'resolved', support_level: 'junior' })).toEqual([
      'done',
      'done',
      'idle',
      'idle',
      'closed',
    ])
    const passed = passedStops([
      {
        from_level: 'junior',
        to_level: 'senior',
        to_status: 'escalated',
        equipment_request_id: null,
      },
      { from_level: null, to_level: null, to_status: null, equipment_request_id: 4 },
    ])
    expect(states({ status: 'resolved', support_level: 'junior' }, passed)).toEqual([
      'done',
      'done',
      'done',
      'done',
      'closed',
    ])
  })
})
