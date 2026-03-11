import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CollapsibleSystemMessage from '../CollapsibleSystemMessage.vue'
import { createMessage } from '@/test-utils/factories'
import { defaultGlobalConfig } from '@/test-utils/mount-config'

vi.mock('@target/composables/useMarkdown', () => ({
  useMarkdown: () => ({
    toHtml: (content: { value: string }) => ({
      value: content.value || '',
    }),
  }),
}))

vi.mock('@target/composables/useStringUtils', () => ({
  useStringUtils: () => ({
    unescapeString: (str: string) => str,
  }),
}))

describe('CollapsibleSystemMessage', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  const mountComponent = (props: {
    message: ReturnType<typeof createMessage>
    isExpanded: boolean
  }) =>
    mount(CollapsibleSystemMessage, {
      props,
      global: defaultGlobalConfig,
    })

  it('renders collapsed state with truncated first line', () => {
    const message = createMessage('msg-1', {
      contents: [
        {
          '@type': 'TextContent',
          '@id': '/contents/1',
          id: '1',
          content: 'First line of message\nSecond line of message',
        },
      ],
    })

    const wrapper = mountComponent({ message, isExpanded: false })

    expect(wrapper.text()).toContain('First line of message')
  })

  it('shows chevron when collapsed and when expanded', () => {
    const message = createMessage('msg-1', {
      contents: [
        {
          '@type': 'TextContent',
          '@id': '/contents/1',
          id: '1',
          content: 'First line of message\nSecond line of message',
        },
      ],
    })

    const wrapperCollapsed = mountComponent({ message, isExpanded: false })
    expect(wrapperCollapsed.find('[aria-label="Expand/Collapse"]').exists()).toBe(true)

    const wrapperExpanded = mountComponent({ message, isExpanded: true })
    expect(wrapperExpanded.find('[aria-label="Expand/Collapse"]').exists()).toBe(true)
  })

  it('emits toggle event when trigger is clicked', async () => {
    const message = createMessage('msg-1', {
      contents: [
        {
          '@type': 'TextContent',
          '@id': '/contents/1',
          id: '1',
          content: 'First line of message\nSecond line of message',
        },
      ],
    })

    const wrapper = mountComponent({ message, isExpanded: false })
    await wrapper.find('button').trigger('click')

    expect(wrapper.emitted('toggle')).toBeTruthy()
  })

  it('shows error indicator when message has error status', () => {
    const message = createMessage('msg-1', {
      metadata: { status: 'error' },
    })

    const wrapper = mountComponent({ message, isExpanded: false })

    expect(wrapper.find('[aria-label="Error indicator"]').exists()).toBe(true)
  })

  it('does not show error indicator when message has no error status', () => {
    const message = createMessage('msg-1')

    const wrapper = mountComponent({ message, isExpanded: false })

    expect(wrapper.find('[aria-label="Error indicator"]').exists()).toBe(false)
  })

  it('does not show chevron for short single-line messages', () => {
    const message = createMessage('msg-1', {
      contents: [
        {
          '@type': 'TextContent',
          '@id': '/contents/1',
          id: '1',
          content: 'Short message',
        },
      ],
    })

    const wrapper = mountComponent({ message, isExpanded: false })

    expect(wrapper.find('[aria-label="Expand/Collapse"]').exists()).toBe(false)
  })

  it('shows chevron for long single-line messages (truncation)', () => {
    const message = createMessage('msg-1', {
      contents: [
        {
          '@type': 'TextContent',
          '@id': '/contents/1',
          id: '1',
          content: 'A'.repeat(100),
        },
      ],
    })

    const wrapper = mountComponent({ message, isExpanded: false })

    expect(wrapper.find('[aria-label="Expand/Collapse"]').exists()).toBe(true)
  })

  it('handles empty message content gracefully', () => {
    const message = createMessage('msg-1', {
      contents: [
        {
          '@type': 'TextContent',
          '@id': '/contents/1',
          id: '1',
          content: '',
        },
      ],
    })

    const wrapper = mountComponent({ message, isExpanded: false })

    expect(wrapper.exists()).toBe(true)
    expect(wrapper.find('[aria-label="Expand/Collapse"]').exists()).toBe(false)
  })
})
