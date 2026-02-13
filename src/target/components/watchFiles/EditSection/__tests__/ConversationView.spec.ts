import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ChatMessageComponent from '~/components/chat/ChatMessage.vue';
import SystemMessagesSection from '~/components/chat/SystemMessagesSection.vue';
import { useChatStore } from '~/stores/chat';
import { useConversationStore } from '~/stores/conversation';
import { MessageRole, MessageStatus, type Message } from '~/types/conversation';
import ConversationView from '../ConversationView.vue';

// Mock vue-i18n
vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string) => key,
    d: () => 'formatted date',
  }),
}));

// Mock useAuth (uses #imports which Vitest can't resolve)
vi.mock('~/composables/useAuth', () => ({
  useAuth: () => ({
    isAuthenticated: { value: true },
    isAuthProviderReady: { value: true },
    getValidToken: () => Promise.resolve('mock-token'),
    logout: vi.fn(),
  }),
}));

// Mock useApi
vi.mock('~/composables/useApi', () => ({
  useApi: () => ({
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  }),
}));

// Mock API functions
vi.mock('~/api/watchFile', () => ({
  retryMessage: vi.fn(),
}));

// Mock useToast
vi.mock('~/composables/useToast', () => ({
  useToast: () => ({
    error: vi.fn(),
    success: vi.fn(),
    showError: vi.fn(),
  }),
}));

// Mock useTimeDisplay
vi.mock('~/composables/useTimeDisplay', () => ({
  useTimeDisplay: () => ({
    formatTime: () => '2 hours ago',
  }),
}));

// Mock useMarkdown
vi.mock('~/composables/useMarkdown', () => ({
  useMarkdown: () => ({
    toHtml: () => ({ value: '' }),
  }),
}));

// Mock useStringUtils
vi.mock('~/composables/useStringUtils', () => ({
  useStringUtils: () => ({
    unescapeString: (str: string) => str,
  }),
}));

// Mock useChatDateDisplay
vi.mock('~/composables/useChatDateDisplay', () => ({
  useChatDateDisplay: () => ({
    getContextualDate: () => 'Today',
    getFullDateTime: () => 'January 1, 2026 at 12:00 PM',
  }),
}));

// Mock @vueuse/core useOnline (used at module level in useMercure)
vi.mock('@vueuse/core', () => ({
  useOnline: () => ({ value: true }),
}));

// Mock sanitize-html directive
const vSanitizeHtml = {
  mounted: (el: HTMLElement, binding: { value: string }) => {
    el.innerHTML = binding.value || '';
  },
  updated: (el: HTMLElement, binding: { value: string }) => {
    el.innerHTML = binding.value || '';
  },
};

const createMessage = (
  id: string,
  role: Message['role'],
  overrides: Partial<Message> = {},
): Message => ({
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
  role,
  status: MessageStatus.SENT,
  retryCount: 0,
  createdAt: new Date().toISOString(),
  ...overrides,
});

const globalConfig = {
  directives: {
    'sanitize-html': vSanitizeHtml,
  },
  stubs: {
    Icon: {
      props: ['icon'],
      template: '<span class="icon" :data-icon="icon"></span>',
    },
    Badge: {
      props: ['number', 'size', 'variant'],
      template: '<span class="badge"></span>',
    },
    InformationMessage: true,
    ConnectionBanner: true,
    ChatMessageComponent: {
      props: ['message'],
      template:
        '<div class="chat-message" :data-message-id="message.id" :data-role="message.role"></div>',
    },
  },
};

describe('ConversationView Integration', () => {
  let conversationStore: ReturnType<typeof useConversationStore>;

  beforeEach(() => {
    setActivePinia(createPinia());
    conversationStore = useConversationStore();
  });

  it('groups 3+ consecutive system messages into SystemMessagesSection', () => {
    const messages = [
      createMessage('msg-1', MessageRole.SYSTEM),
      createMessage('msg-2', MessageRole.SYSTEM),
      createMessage('msg-3', MessageRole.SYSTEM),
      createMessage('msg-4', MessageRole.SYSTEM),
      createMessage('msg-5', MessageRole.MODEL),
    ];
    conversationStore.setMessages(messages);

    const wrapper = mount(ConversationView, {
      props: {
        isLoading: false,
      },
      global: globalConfig,
    });

    // Should render SystemMessagesSection for the 4 consecutive system messages
    const systemSection = wrapper.findComponent(SystemMessagesSection);
    expect(systemSection.exists()).toBe(true);

    // The section should receive 4 messages
    expect(systemSection.props('messages')).toHaveLength(4);
  });

  it('does NOT group user, model, or system_error messages', () => {
    const messages = [
      createMessage('msg-1', MessageRole.USER),
      createMessage('msg-2', MessageRole.MODEL),
      createMessage('msg-3', MessageRole.SYSTEM_ERROR),
      createMessage('msg-4', MessageRole.USER),
    ];
    conversationStore.setMessages(messages);

    const wrapper = mount(ConversationView, {
      props: {
        isLoading: false,
      },
      global: globalConfig,
    });

    // Should NOT render any SystemMessagesSection
    const systemSection = wrapper.findComponent(SystemMessagesSection);
    expect(systemSection.exists()).toBe(false);

    // Should render individual ChatMessage components
    const chatMessages = wrapper.findAllComponents(ChatMessageComponent);
    expect(chatMessages.length).toBe(4);
  });

  it('does NOT include loading skeletons in system message groups', () => {
    const messages = [
      createMessage('msg-1', MessageRole.SYSTEM),
      createMessage('msg-2', MessageRole.SYSTEM),
      createMessage('msg-3', MessageRole.SYSTEM, { loading: true }),
      createMessage('msg-4', MessageRole.MODEL),
    ];
    conversationStore.setMessages(messages);

    const wrapper = mount(ConversationView, {
      props: {
        isLoading: false,
      },
      global: globalConfig,
    });

    // With MIN_SYSTEM_GROUP_SIZE=2, 2 non-loading system messages should be grouped
    const systemSection = wrapper.findComponent(SystemMessagesSection);
    expect(systemSection.exists()).toBe(true);

    // Loading message should be rendered separately (not in the group)
    const chatMessages = wrapper.findAllComponents(ChatMessageComponent);
    // Loading skeleton message + model message = 2 separate ChatMessages
    expect(chatMessages.length).toBe(2);
  });

  it('maintains global accordion behavior - expanding a message in one group collapses message in another', async () => {
    const messages = [
      createMessage('msg-1', MessageRole.SYSTEM),
      createMessage('msg-2', MessageRole.SYSTEM),
      createMessage('msg-3', MessageRole.SYSTEM),
      createMessage('msg-4', MessageRole.MODEL),
      createMessage('msg-5', MessageRole.SYSTEM),
      createMessage('msg-6', MessageRole.SYSTEM),
      createMessage('msg-7', MessageRole.SYSTEM),
    ];
    conversationStore.setMessages(messages);
    const chatStore = useChatStore();

    mount(ConversationView, {
      props: {
        isLoading: false,
      },
      global: globalConfig,
    });

    // Expand a message in first group
    chatStore.toggleMessage('msg-1');
    expect(chatStore.isMessageExpanded('msg-1')).toBe(true);

    // Expand a message in second group - should collapse first
    chatStore.toggleMessage('msg-5');
    expect(chatStore.isMessageExpanded('msg-5')).toBe(true);
    expect(chatStore.isMessageExpanded('msg-1')).toBe(false);
  });

  it('handles Mercure real-time updates correctly without breaking grouping', async () => {
    // Initial messages
    const initialMessages = [
      createMessage('msg-1', MessageRole.SYSTEM),
      createMessage('msg-2', MessageRole.SYSTEM),
      createMessage('msg-3', MessageRole.SYSTEM),
    ];
    conversationStore.setMessages(initialMessages);

    const wrapper = mount(ConversationView, {
      props: {
        isLoading: false,
      },
      global: globalConfig,
    });

    // Should have a system section with 3 messages
    let systemSection = wrapper.findComponent(SystemMessagesSection);
    expect(systemSection.exists()).toBe(true);
    expect(systemSection.props('messages')).toHaveLength(3);

    // Simulate Mercure update by adding a new message to the store
    conversationStore.addOrUpdateMessage(
      createMessage('msg-4', MessageRole.SYSTEM),
    );

    // Wait for Vue to update
    await wrapper.vm.$nextTick();

    // Should still have a system section, now with 4 messages
    systemSection = wrapper.findComponent(SystemMessagesSection);
    expect(systemSection.exists()).toBe(true);
    expect(systemSection.props('messages')).toHaveLength(4);
  });

  it('groups exactly 2 consecutive system messages (MIN_SYSTEM_GROUP_SIZE=2)', () => {
    const messages = [
      createMessage('msg-1', MessageRole.SYSTEM),
      createMessage('msg-2', MessageRole.SYSTEM),
      createMessage('msg-3', MessageRole.MODEL),
    ];
    conversationStore.setMessages(messages);

    const wrapper = mount(ConversationView, {
      props: {
        isLoading: false,
      },
      global: globalConfig,
    });

    // With MIN_SYSTEM_GROUP_SIZE=2, 2 system messages should be grouped
    const systemSection = wrapper.findComponent(SystemMessagesSection);
    expect(systemSection.exists()).toBe(true);
    expect(systemSection.props('messages')).toHaveLength(2);

    // Should render only 1 ChatMessage for the model message
    const chatMessages = wrapper.findAllComponents(ChatMessageComponent);
    expect(chatMessages.length).toBe(1);
  });

  it('does NOT group single system message', () => {
    const messages = [
      createMessage('msg-1', MessageRole.SYSTEM),
      createMessage('msg-2', MessageRole.MODEL),
    ];
    conversationStore.setMessages(messages);

    const wrapper = mount(ConversationView, {
      props: {
        isLoading: false,
      },
      global: globalConfig,
    });

    // Should NOT render SystemMessagesSection for single system message
    const systemSection = wrapper.findComponent(SystemMessagesSection);
    expect(systemSection.exists()).toBe(false);

    // Should render individual ChatMessage for each
    const chatMessages = wrapper.findAllComponents(ChatMessageComponent);
    expect(chatMessages.length).toBe(2);
  });

  it('handles complex mixed message sequences with multiple system groups', () => {
    // Complex sequence: system-user-system-system-system-model-system
    const messages = [
      createMessage('msg-1', MessageRole.SYSTEM), // Single system - no group
      createMessage('msg-2', MessageRole.USER),
      createMessage('msg-3', MessageRole.SYSTEM), // Start of group
      createMessage('msg-4', MessageRole.SYSTEM),
      createMessage('msg-5', MessageRole.SYSTEM), // End of group (3 messages)
      createMessage('msg-6', MessageRole.MODEL),
      createMessage('msg-7', MessageRole.SYSTEM), // Single system - no group
    ];
    conversationStore.setMessages(messages);

    const wrapper = mount(ConversationView, {
      props: {
        isLoading: false,
      },
      global: globalConfig,
    });

    // Should render exactly 1 SystemMessagesSection for the 3 consecutive system messages
    const systemSections = wrapper.findAllComponents(SystemMessagesSection);
    expect(systemSections.length).toBe(1);
    expect(systemSections[0]?.props('messages')).toHaveLength(3);

    // Should render ChatMessage for: msg-1 (system), msg-2 (user), msg-6 (model), msg-7 (system)
    const chatMessages = wrapper.findAllComponents(ChatMessageComponent);
    expect(chatMessages.length).toBe(4);
  });

  it('preserves expanded state when Mercure adds new messages', async () => {
    const initialMessages = [
      createMessage('msg-1', MessageRole.SYSTEM),
      createMessage('msg-2', MessageRole.SYSTEM),
      createMessage('msg-3', MessageRole.SYSTEM),
    ];
    conversationStore.setMessages(initialMessages);
    const chatStore = useChatStore();

    const wrapper = mount(ConversationView, {
      props: {
        isLoading: false,
      },
      global: globalConfig,
    });

    // Expand a message
    chatStore.toggleMessage('msg-2');
    expect(chatStore.isMessageExpanded('msg-2')).toBe(true);

    // Simulate Mercure update - add a new system message to the store
    conversationStore.addOrUpdateMessage(
      createMessage('msg-4', MessageRole.SYSTEM),
    );

    // Wait for Vue to update
    await wrapper.vm.$nextTick();

    // The expanded state should be preserved
    expect(chatStore.isMessageExpanded('msg-2')).toBe(true);
    // New message should not be expanded
    expect(chatStore.isMessageExpanded('msg-4')).toBe(false);
  });
});
