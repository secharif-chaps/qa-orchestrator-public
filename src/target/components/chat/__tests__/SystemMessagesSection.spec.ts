import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useChatStore } from '~/stores/chat'
import { MessageRole, MessageStatus, type Message } from '~/types/conversation'
import CollapsibleSystemMessage from '../CollapsibleSystemMessage.vue'
import SystemMessagesSection from '../SystemMessagesSection.vue'

// Mock vue-i18n
vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string, params?: Record<string, unknown>, count?: number) => {
      if (key === 'watch_files.chat.system_messages.view_older') {
        return `View ${count} older actions`
      }
      if (key === 'watch_files.chat.system_messages.hide_older') {
        return 'Hide older actions'
      }
      return key
    },
  }),
}))

// Mock useTimeDisplay
vi.mock('~/composables/useTimeDisplay', () => ({
  useTimeDisplay: () => ({
    formatTime: () => '2 hours ago',
  }),
}))

// Mock useMarkdown
vi.mock('~/composables/useMarkdown', () => ({
  useMarkdown: () => ({
    toHtml: () => ({ value: '' }),
  }),
}))

// Mock useStringUtils
vi.mock('~/composables/useStringUtils', () => ({
  useStringUtils: () => ({
    unescapeString: (str: string) => str,
  }),
}))

// Mock sanitize-html directive
const vSanitizeHtml = {
  mounted: (el: HTMLElement, binding: { value: string }) => {
    el.innerHTML = binding.value
  },
  updated: (el: HTMLElement, binding: { value: string }) => {
    el.innerHTML = binding.value
  },
}

const createMessage = (id: string, overrides: Partial<Message> = {}): Message => ({
  id,
  '@type': 'Message',
  contents: [
    {
      '@type': 'TextContent',
      '@id': `/contents/${id}`,
      id,
      content: `Message content ${id}`,
    },
  ],
  role: MessageRole.SYSTEM,
  status: MessageStatus.SENT,
  retryCount: 0,
  createdAt: new Date().toISOString(),
  ...overrides,
})

const createMessages = (count: number): Message[] => {
  return Array.from({ length: count }, (_, i) => createMessage(`msg-${i + 1}`))
}

describe('SystemMessagesSection', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('shows only last 3 messages by default when there are more than 3', () => {
    const messages = createMessages(5)

    const wrapper = mount(SystemMessagesSection, {
      props: {
        messages,
        groupId: 'group-1',
      },
      global: {
        directives: {
          'sanitize-html': vSanitizeHtml,
        },
        stubs: {
          Icon: {
            props: ['icon'],
            template: '<span class="icon" :data-icon="icon"></span>',
          },
        },
      },
    })

    // Should show "View X older" button
    expect(wrapper.text()).toContain('View 2 older actions')

    // Should only render 3 CollapsibleSystemMessage components (the visible ones)
    const collapsibleMessages = wrapper.findAllComponents(CollapsibleSystemMessage)
    expect(collapsibleMessages.length).toBe(3)
  })

  it('shows all messages when "View X older" button is clicked', async () => {
    const messages = createMessages(5)
    const chatStore = useChatStore()

    const wrapper = mount(SystemMessagesSection, {
      props: {
        messages,
        groupId: 'group-1',
      },
      global: {
        directives: {
          'sanitize-html': vSanitizeHtml,
        },
        stubs: {
          Icon: {
            props: ['icon'],
            template: '<span class="icon" :data-icon="icon"></span>',
          },
        },
      },
    })

    // Find and click the "View older" button
    const viewOlderButton = wrapper.find('button')
    expect(viewOlderButton.text()).toContain('View 2 older actions')
    await viewOlderButton.trigger('click')

    // Verify store was called
    expect(chatStore.isOlderMessagesVisible('group-1')).toBe(true)

    // Re-mount to reflect state change
    const wrapperAfter = mount(SystemMessagesSection, {
      props: {
        messages,
        groupId: 'group-1',
      },
      global: {
        directives: {
          'sanitize-html': vSanitizeHtml,
        },
        stubs: {
          Icon: {
            props: ['icon'],
            template: '<span class="icon" :data-icon="icon"></span>',
          },
        },
      },
    })

    // Should now show all 5 messages
    const allCollapsibleMessages = wrapperAfter.findAllComponents(CollapsibleSystemMessage)
    expect(allCollapsibleMessages.length).toBe(5)

    // Should show "Hide older actions" button
    expect(wrapperAfter.text()).toContain('Hide older actions')
  })

  it('shows all messages without "View older" button when 3 or fewer messages', () => {
    const messages = createMessages(3)

    const wrapper = mount(SystemMessagesSection, {
      props: {
        messages,
        groupId: 'group-1',
      },
      global: {
        directives: {
          'sanitize-html': vSanitizeHtml,
        },
        stubs: {
          Icon: {
            props: ['icon'],
            template: '<span class="icon" :data-icon="icon"></span>',
          },
        },
      },
    })

    // Should not show "View X older" button
    expect(wrapper.text()).not.toContain('View')

    // Should render all 3 messages
    const collapsibleMessages = wrapper.findAllComponents(CollapsibleSystemMessage)
    expect(collapsibleMessages.length).toBe(3)
  })

  it('hides older messages when "Hide older actions" button is clicked', async () => {
    const messages = createMessages(5)
    const chatStore = useChatStore()

    // First show older messages
    chatStore.showOlderMessages('group-1')

    const wrapper = mount(SystemMessagesSection, {
      props: {
        messages,
        groupId: 'group-1',
      },
      global: {
        directives: {
          'sanitize-html': vSanitizeHtml,
        },
        stubs: {
          Icon: {
            props: ['icon'],
            template: '<span class="icon" :data-icon="icon"></span>',
          },
        },
      },
    })

    // Find and click the "Hide older" button
    const hideButton = wrapper
      .findAll('button')
      .find((btn) => btn.text().includes('Hide older actions'))
    expect(hideButton).toBeDefined()
    await hideButton!.trigger('click')

    // Verify store was called
    expect(chatStore.isOlderMessagesVisible('group-1')).toBe(false)
  })
})
