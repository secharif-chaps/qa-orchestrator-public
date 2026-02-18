import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { type Message, MessageRole, MessageStatus } from '~/types/conversation'
import CollapsibleSystemMessage from '../CollapsibleSystemMessage.vue'

// Mock useMarkdown
vi.mock('~/composables/useMarkdown', () => ({
  useMarkdown: () => ({
    toHtml: (content: { value: string }) => ({
      value: content.value || '',
    }),
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

const createMessage = (overrides: Partial<Message> = {}): Message => ({
  id: 'msg-1',
  '@type': 'Message',
  contents: [
    {
      '@type': 'TextContent',
      '@id': '/contents/1',
      id: '1',
      content: 'First line of message\nSecond line of message',
    },
  ],
  role: MessageRole.SYSTEM,
  status: MessageStatus.SENT,
  retryCount: 0,
  createdAt: new Date().toISOString(),
  ...overrides,
})

describe('CollapsibleSystemMessage', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders collapsed state with truncated first line', () => {
    const message = createMessage()

    const wrapper = mount(CollapsibleSystemMessage, {
      props: {
        message,
        isExpanded: false,
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

    // Should show first line text
    expect(wrapper.text()).toContain('First line of message')
  })

  it('shows chevron-down when collapsed and chevron-up when expanded', async () => {
    const message = createMessage()

    // Test collapsed state
    const wrapperCollapsed = mount(CollapsibleSystemMessage, {
      props: {
        message,
        isExpanded: false,
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

    const collapsedIcon = wrapperCollapsed.find('[data-icon="fa-chevron-down"]')
    expect(collapsedIcon.exists()).toBe(true)

    // Test expanded state
    const wrapperExpanded = mount(CollapsibleSystemMessage, {
      props: {
        message,
        isExpanded: true,
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

    const expandedIcon = wrapperExpanded.find('[data-icon="fa-chevron-up"]')
    expect(expandedIcon.exists()).toBe(true)
  })

  it('emits toggle event when trigger is clicked', async () => {
    const message = createMessage()

    const wrapper = mount(CollapsibleSystemMessage, {
      props: {
        message,
        isExpanded: false,
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

    // Find the trigger button and click it
    const trigger = wrapper.find('button')
    await trigger.trigger('click')

    // Should emit toggle event
    expect(wrapper.emitted('toggle')).toBeTruthy()
  })

  it('shows error indicator when message has error status', () => {
    const message = createMessage({
      metadata: { status: 'error' },
    })

    const wrapper = mount(CollapsibleSystemMessage, {
      props: {
        message,
        isExpanded: false,
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

    // Should have error indicator (red dot)
    const errorIndicator = wrapper.find('.bg-red-500')
    expect(errorIndicator.exists()).toBe(true)
  })

  it('does not show error indicator when message has no error status', () => {
    const message = createMessage()

    const wrapper = mount(CollapsibleSystemMessage, {
      props: {
        message,
        isExpanded: false,
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

    // Should not have error indicator
    const errorIndicator = wrapper.find('.bg-red-500')
    expect(errorIndicator.exists()).toBe(false)
  })

  // Short single-line message should not be collapsible
  it('does not show chevron for short single-line messages', () => {
    const message = createMessage({
      contents: [
        {
          '@type': 'TextContent',
          '@id': '/contents/1',
          id: '1',
          content: 'Short message', // Less than 80 chars, single line
        },
      ],
    })

    const wrapper = mount(CollapsibleSystemMessage, {
      props: {
        message,
        isExpanded: false,
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

    // Should NOT have chevron icon (not collapsible)
    const chevronDown = wrapper.find('[data-icon="fa-chevron-down"]')
    const chevronUp = wrapper.find('[data-icon="fa-chevron-up"]')
    expect(chevronDown.exists()).toBe(false)
    expect(chevronUp.exists()).toBe(false)
  })

  // Long single-line message should be collapsible
  it('shows chevron for long single-line messages (truncation)', () => {
    const longText = 'A'.repeat(100) // More than 80 chars threshold
    const message = createMessage({
      contents: [
        {
          '@type': 'TextContent',
          '@id': '/contents/1',
          id: '1',
          content: longText,
        },
      ],
    })

    const wrapper = mount(CollapsibleSystemMessage, {
      props: {
        message,
        isExpanded: false,
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

    // Should have chevron icon (collapsible due to truncation)
    const chevronDown = wrapper.find('[data-icon="fa-chevron-down"]')
    expect(chevronDown.exists()).toBe(true)
  })

  // Gap Analysis Test: Empty message content
  it('handles empty message content gracefully', () => {
    const message = createMessage({
      contents: [
        {
          '@type': 'TextContent',
          '@id': '/contents/1',
          id: '1',
          content: '',
        },
      ],
    })

    const wrapper = mount(CollapsibleSystemMessage, {
      props: {
        message,
        isExpanded: false,
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

    // Should render without errors
    expect(wrapper.exists()).toBe(true)
    // Empty content is not collapsible (no chevron)
    const chevronDown = wrapper.find('[data-icon="fa-chevron-down"]')
    expect(chevronDown.exists()).toBe(false)
  })
})
