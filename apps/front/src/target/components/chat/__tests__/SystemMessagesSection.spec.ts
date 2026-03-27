import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import SystemMessagesSection from '../SystemMessagesSection.vue'
import CollapsibleSystemMessage from '../CollapsibleSystemMessage.vue'
import { useChatStore } from '@target/stores/chat'
import { createMessages } from '@/test-utils/factories'
import { MessageRole } from '@target/types/conversation'
import { defaultGlobalConfig, buttonStub } from '@/test-utils/mount-config'

vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string, _params?: Record<string, unknown>, count?: number) => {
      if (key === 'target.watchFiles.chat.system_messages.view_older') {
        return `View ${count} older actions`
      }
      if (key === 'target.watchFiles.chat.system_messages.hide_older') {
        return 'Hide older actions'
      }
      return key
    },
  }),
}))

vi.mock('@target/composables/useTimeDisplay', () => ({
  useTimeDisplay: () => ({
    formatTime: () => '2 hours ago',
  }),
}))

vi.mock('@target/composables/useMarkdown', () => ({
  useMarkdown: () => ({
    toHtml: () => ({ value: '' }),
  }),
}))

vi.mock('@target/composables/useStringUtils', () => ({
  useStringUtils: () => ({
    unescapeString: (str: string) => str,
  }),
}))

const globalConfig = {
  ...defaultGlobalConfig,
  stubs: {
    ...defaultGlobalConfig.stubs,
    Button: buttonStub,
  },
}

describe('SystemMessagesSection', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('shows only last 3 messages by default when there are more than 3', () => {
    const messages = createMessages(5, { role: MessageRole.SYSTEM })

    const wrapper = mount(SystemMessagesSection, {
      props: { messages, groupId: 'group-1' },
      global: globalConfig,
    })

    expect(wrapper.text()).toContain('View 2 older actions')

    const collapsibleMessages = wrapper.findAllComponents(CollapsibleSystemMessage)
    expect(collapsibleMessages.length).toBe(3)
  })

  it('shows all messages when "View X older" button is clicked', async () => {
    const messages = createMessages(5, { role: MessageRole.SYSTEM })
    const chatStore = useChatStore()

    const wrapper = mount(SystemMessagesSection, {
      props: { messages, groupId: 'group-1' },
      global: globalConfig,
    })

    const viewOlderButton = wrapper.find('button')
    expect(viewOlderButton.text()).toContain('View 2 older actions')
    await viewOlderButton.trigger('click')

    expect(chatStore.isOlderMessagesVisible('group-1')).toBe(true)

    await wrapper.vm.$nextTick()

    const allCollapsibleMessages = wrapper.findAllComponents(CollapsibleSystemMessage)
    expect(allCollapsibleMessages.length).toBe(5)
    expect(wrapper.text()).toContain('Hide older actions')
  })

  it('shows all messages without "View older" button when 3 or fewer messages', () => {
    const messages = createMessages(3, { role: MessageRole.SYSTEM })

    const wrapper = mount(SystemMessagesSection, {
      props: { messages, groupId: 'group-1' },
      global: defaultGlobalConfig,
    })

    expect(wrapper.text()).not.toContain('View')

    const collapsibleMessages = wrapper.findAllComponents(CollapsibleSystemMessage)
    expect(collapsibleMessages.length).toBe(3)
  })

  it('hides older messages when "Hide older actions" button is clicked', async () => {
    const messages = createMessages(5, { role: MessageRole.SYSTEM })
    const chatStore = useChatStore()

    chatStore.showOlderMessages('group-1')

    const wrapper = mount(SystemMessagesSection, {
      props: { messages, groupId: 'group-1' },
      global: globalConfig,
    })

    const hideButton = wrapper
      .findAll('button')
      .find((btn) => btn.text().includes('Hide older actions'))
    expect(hideButton).toBeDefined()
    await hideButton!.trigger('click')

    expect(chatStore.isOlderMessagesVisible('group-1')).toBe(false)
  })
})
