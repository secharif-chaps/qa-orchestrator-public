import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import { useChatStore } from '~/stores/chat'

describe('useChatStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('has correct initial state', () => {
    const store = useChatStore()

    expect(store.expandedMessageId).toBeNull()
    expect(store.visibleOlderGroups.size).toBe(0)
  })

  it('toggleMessage expands a message and collapses previously expanded message (accordion behavior)', () => {
    const store = useChatStore()

    // Expand first message
    store.toggleMessage('message-1')
    expect(store.expandedMessageId).toBe('message-1')
    expect(store.isMessageExpanded('message-1')).toBe(true)
    expect(store.isMessageExpanded('message-2')).toBe(false)

    // Expand second message - should collapse first (accordion behavior)
    store.toggleMessage('message-2')
    expect(store.expandedMessageId).toBe('message-2')
    expect(store.isMessageExpanded('message-1')).toBe(false)
    expect(store.isMessageExpanded('message-2')).toBe(true)

    // Toggle same message again - should collapse it
    store.toggleMessage('message-2')
    expect(store.expandedMessageId).toBeNull()
    expect(store.isMessageExpanded('message-2')).toBe(false)
  })

  it('showOlderMessages adds groupId to visibleOlderGroups', () => {
    const store = useChatStore()

    expect(store.isOlderMessagesVisible('group-1')).toBe(false)

    store.showOlderMessages('group-1')
    expect(store.isOlderMessagesVisible('group-1')).toBe(true)
    expect(store.visibleOlderGroups.has('group-1')).toBe(true)

    // Adding another group
    store.showOlderMessages('group-2')
    expect(store.isOlderMessagesVisible('group-1')).toBe(true)
    expect(store.isOlderMessagesVisible('group-2')).toBe(true)

    // hideOlderMessages removes groupId
    store.hideOlderMessages('group-1')
    expect(store.isOlderMessagesVisible('group-1')).toBe(false)
    expect(store.isOlderMessagesVisible('group-2')).toBe(true)
  })

  it('$reset clears all state to initial values', () => {
    const store = useChatStore()

    // Set some state
    store.toggleMessage('message-1')
    store.showOlderMessages('group-1')
    store.showOlderMessages('group-2')

    expect(store.expandedMessageId).toBe('message-1')
    expect(store.visibleOlderGroups.size).toBe(2)

    // Reset
    store.$reset()

    expect(store.expandedMessageId).toBeNull()
    expect(store.visibleOlderGroups.size).toBe(0)
    expect(store.isMessageExpanded('message-1')).toBe(false)
    expect(store.isOlderMessagesVisible('group-1')).toBe(false)
  })
})
