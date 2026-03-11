import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import ConversationView from '../ConversationView.vue'
import SystemMessagesSection from '@target/components/chat/SystemMessagesSection.vue'
import ChatMessageComponent from '@target/components/chat/ChatMessage.vue'
import { MessageRole } from '@target/types/conversation'
import { useChatStore } from '@target/stores/chat'
import { useConversationStore } from '@target/stores/conversation'
import { createMessage } from '@/test-utils/factories'
import { defaultGlobalConfig, badgeStub } from '@/test-utils/mount-config'

vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string) => key,
    d: () => 'formatted date',
  }),
}))

vi.mock('@target/composables/useAuth', () => ({
  useAuth: () => ({
    isAuthenticated: { value: true },
    isAuthProviderReady: { value: true },
    getValidToken: () => Promise.resolve('mock-token'),
    logout: vi.fn(),
  }),
}))

vi.mock('@target/composables/useApi', () => ({
  useApi: () => ({
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  }),
}))

vi.mock('@target/api/watchFile', () => ({
  retryMessage: vi.fn(),
}))

vi.mock('@target/composables/useToast', () => ({
  useToast: () => ({
    error: vi.fn(),
    success: vi.fn(),
    showError: vi.fn(),
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

vi.mock('@target/composables/useChatDateDisplay', () => ({
  useChatDateDisplay: () => ({
    getContextualDate: () => 'Today',
    getFullDateTime: () => 'January 1, 2026 at 12:00 PM',
  }),
}))

vi.mock('@vueuse/core', () => ({
  useOnline: () => ({ value: true }),
}))

const globalConfig = {
  ...defaultGlobalConfig,
  stubs: {
    ...defaultGlobalConfig.stubs,
    Badge: badgeStub,
    InformationMessage: true,
    ConnectionBanner: true,
    ChatMessageComponent: {
      props: ['message'],
      template:
        '<div class="chat-message" :data-message-id="message.id" :data-role="message.role"></div>',
    },
  },
}

describe('ConversationView Integration', () => {
  let conversationStore: ReturnType<typeof useConversationStore>

  beforeEach(() => {
    setActivePinia(createPinia())
    conversationStore = useConversationStore()
  })

  it('groups 3+ consecutive system messages into SystemMessagesSection', () => {
    conversationStore.setMessages([
      createMessage('msg-1', { role: MessageRole.SYSTEM }),
      createMessage('msg-2', { role: MessageRole.SYSTEM }),
      createMessage('msg-3', { role: MessageRole.SYSTEM }),
      createMessage('msg-4', { role: MessageRole.SYSTEM }),
      createMessage('msg-5', { role: MessageRole.MODEL }),
    ])

    const wrapper = mount(ConversationView, {
      props: { isLoading: false },
      global: globalConfig,
    })

    const systemSection = wrapper.findComponent(SystemMessagesSection)
    expect(systemSection.exists()).toBe(true)
    expect(systemSection.props('messages')).toHaveLength(4)
  })

  it('does NOT group user, model, or system_error messages', () => {
    conversationStore.setMessages([
      createMessage('msg-1', { role: MessageRole.USER }),
      createMessage('msg-2', { role: MessageRole.MODEL }),
      createMessage('msg-3', { role: MessageRole.SYSTEM_ERROR }),
      createMessage('msg-4', { role: MessageRole.USER }),
    ])

    const wrapper = mount(ConversationView, {
      props: { isLoading: false },
      global: globalConfig,
    })

    expect(wrapper.findComponent(SystemMessagesSection).exists()).toBe(false)

    const chatMessages = wrapper.findAllComponents(ChatMessageComponent)
    expect(chatMessages.length).toBe(4)
  })

  it('does NOT include loading skeletons in system message groups', () => {
    conversationStore.setMessages([
      createMessage('msg-1', { role: MessageRole.SYSTEM }),
      createMessage('msg-2', { role: MessageRole.SYSTEM }),
      createMessage('msg-3', { role: MessageRole.SYSTEM, loading: true }),
      createMessage('msg-4', { role: MessageRole.MODEL }),
    ])

    const wrapper = mount(ConversationView, {
      props: { isLoading: false },
      global: globalConfig,
    })

    const systemSection = wrapper.findComponent(SystemMessagesSection)
    expect(systemSection.exists()).toBe(true)

    const chatMessages = wrapper.findAllComponents(ChatMessageComponent)
    expect(chatMessages.length).toBe(2)
  })

  it('maintains global accordion behavior across groups', async () => {
    conversationStore.setMessages([
      createMessage('msg-1', { role: MessageRole.SYSTEM }),
      createMessage('msg-2', { role: MessageRole.SYSTEM }),
      createMessage('msg-3', { role: MessageRole.SYSTEM }),
      createMessage('msg-4', { role: MessageRole.MODEL }),
      createMessage('msg-5', { role: MessageRole.SYSTEM }),
      createMessage('msg-6', { role: MessageRole.SYSTEM }),
      createMessage('msg-7', { role: MessageRole.SYSTEM }),
    ])
    const chatStore = useChatStore()

    mount(ConversationView, {
      props: { isLoading: false },
      global: globalConfig,
    })

    chatStore.toggleMessage('msg-1')
    expect(chatStore.isMessageExpanded('msg-1')).toBe(true)

    chatStore.toggleMessage('msg-5')
    expect(chatStore.isMessageExpanded('msg-5')).toBe(true)
    expect(chatStore.isMessageExpanded('msg-1')).toBe(false)
  })

  it('handles Mercure real-time updates correctly without breaking grouping', async () => {
    conversationStore.setMessages([
      createMessage('msg-1', { role: MessageRole.SYSTEM }),
      createMessage('msg-2', { role: MessageRole.SYSTEM }),
      createMessage('msg-3', { role: MessageRole.SYSTEM }),
    ])

    const wrapper = mount(ConversationView, {
      props: { isLoading: false },
      global: globalConfig,
    })

    let systemSection = wrapper.findComponent(SystemMessagesSection)
    expect(systemSection.exists()).toBe(true)
    expect(systemSection.props('messages')).toHaveLength(3)

    conversationStore.addOrUpdateMessage(createMessage('msg-4', { role: MessageRole.SYSTEM }))
    await wrapper.vm.$nextTick()

    systemSection = wrapper.findComponent(SystemMessagesSection)
    expect(systemSection.exists()).toBe(true)
    expect(systemSection.props('messages')).toHaveLength(4)
  })

  it('groups exactly 2 consecutive system messages (MIN_SYSTEM_GROUP_SIZE=2)', () => {
    conversationStore.setMessages([
      createMessage('msg-1', { role: MessageRole.SYSTEM }),
      createMessage('msg-2', { role: MessageRole.SYSTEM }),
      createMessage('msg-3', { role: MessageRole.MODEL }),
    ])

    const wrapper = mount(ConversationView, {
      props: { isLoading: false },
      global: globalConfig,
    })

    const systemSection = wrapper.findComponent(SystemMessagesSection)
    expect(systemSection.exists()).toBe(true)
    expect(systemSection.props('messages')).toHaveLength(2)

    const chatMessages = wrapper.findAllComponents(ChatMessageComponent)
    expect(chatMessages.length).toBe(1)
  })

  it('does NOT group single system message', () => {
    conversationStore.setMessages([
      createMessage('msg-1', { role: MessageRole.SYSTEM }),
      createMessage('msg-2', { role: MessageRole.MODEL }),
    ])

    const wrapper = mount(ConversationView, {
      props: { isLoading: false },
      global: globalConfig,
    })

    expect(wrapper.findComponent(SystemMessagesSection).exists()).toBe(false)

    const chatMessages = wrapper.findAllComponents(ChatMessageComponent)
    expect(chatMessages.length).toBe(2)
  })

  it('handles complex mixed message sequences with multiple system groups', () => {
    conversationStore.setMessages([
      createMessage('msg-1', { role: MessageRole.SYSTEM }),
      createMessage('msg-2', { role: MessageRole.USER }),
      createMessage('msg-3', { role: MessageRole.SYSTEM }),
      createMessage('msg-4', { role: MessageRole.SYSTEM }),
      createMessage('msg-5', { role: MessageRole.SYSTEM }),
      createMessage('msg-6', { role: MessageRole.MODEL }),
      createMessage('msg-7', { role: MessageRole.SYSTEM }),
    ])

    const wrapper = mount(ConversationView, {
      props: { isLoading: false },
      global: globalConfig,
    })

    const systemSections = wrapper.findAllComponents(SystemMessagesSection)
    expect(systemSections.length).toBe(1)
    expect(systemSections[0]?.props('messages')).toHaveLength(3)

    const chatMessages = wrapper.findAllComponents(ChatMessageComponent)
    expect(chatMessages.length).toBe(4)
  })

  it('preserves expanded state when Mercure adds new messages', async () => {
    conversationStore.setMessages([
      createMessage('msg-1', { role: MessageRole.SYSTEM }),
      createMessage('msg-2', { role: MessageRole.SYSTEM }),
      createMessage('msg-3', { role: MessageRole.SYSTEM }),
    ])
    const chatStore = useChatStore()

    const wrapper = mount(ConversationView, {
      props: { isLoading: false },
      global: globalConfig,
    })

    chatStore.toggleMessage('msg-2')
    expect(chatStore.isMessageExpanded('msg-2')).toBe(true)

    conversationStore.addOrUpdateMessage(createMessage('msg-4', { role: MessageRole.SYSTEM }))
    await wrapper.vm.$nextTick()

    expect(chatStore.isMessageExpanded('msg-2')).toBe(true)
    expect(chatStore.isMessageExpanded('msg-4')).toBe(false)
  })
})
