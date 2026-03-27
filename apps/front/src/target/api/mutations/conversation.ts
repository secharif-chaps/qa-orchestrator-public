import { defineMutation, useMutation, useQueryCache } from '@pinia/colada'
import { CONVERSATION_QUERY_KEYS } from '@target/api/queries/conversation'
import {
  addMessage,
  cancelConversation,
  getOlderConversationMessages,
  retryMessage,
} from '@target/api/watchFile'
import { useToast } from '@target/composables/useToast'
import { useConversationStore } from '@target/stores/conversation'
import type { Message } from '@target/types/conversation'
import { MessageRole, MessageStatus } from '@target/types/conversation'
import { useI18n } from 'vue-i18n'

export const useAddMessage = defineMutation(() => {
  const queryCache = useQueryCache()
  const conversationStore = useConversationStore()
  const toast = useToast()
  const { t } = useI18n()

  const { mutate, ...mutation } = useMutation({
    mutation: ({ conversationId, message }: { conversationId: string; message: string }) =>
      addMessage(conversationId, message),
    onMutate({ message }) {
      const pendingMessage = {
        id: 'temp-' + Date.now(),
        '@type': 'Message' as const,
        contents: [
          {
            '@type': 'TextContent' as const,
            '@id': '/contents/temp-' + Date.now(),
            id: 'temp-' + Date.now(),
            content: message,
          },
        ],
        role: MessageRole.USER,
        status: MessageStatus.PENDING,
        retryCount: 0,
        createdAt: new Date().toISOString(),
      }
      conversationStore.addOrUpdateMessage(pendingMessage)
      conversationStore.setWaitingForAI(true)
      return { pendingMessageId: pendingMessage.id }
    },
    onSettled(_, __, { conversationId }) {
      queryCache.invalidateQueries({
        key: CONVERSATION_QUERY_KEYS.messages(conversationId),
      })
    },
    onSuccess(_, __, { pendingMessageId }) {
      conversationStore.updateMessageStatus(pendingMessageId, MessageStatus.SENT)
    },
    onError(error, _, context) {
      if (context?.pendingMessageId) {
        conversationStore.updateMessageStatus(context.pendingMessageId, MessageStatus.ERROR)
      }
      conversationStore.setWaitingForAI(false)
      toast.error(
        t('target.watchFiles.chat.message.send_failed_title'),
        t('target.watchFiles.chat.message.send_failed_description'),
      )
      console.error('Failed to send message:', error)
    },
  })
  return { ...mutation, addMessage: mutate }
})

export const useRetryMessage = defineMutation(() => {
  const queryCache = useQueryCache()
  const conversationStore = useConversationStore()
  const toast = useToast()
  const { t } = useI18n()

  const { mutate, ...mutation } = useMutation({
    mutation: async ({
      message,
      conversationId,
    }: {
      message: Message
      conversationId: string
    }): Promise<{ isPending: boolean; updatedMessage?: Message }> => {
      message.retryCount += 1
      conversationStore.addOrUpdateMessage(message)
      conversationStore.setWaitingForAI(true)

      const isPendingMessage = message.id.startsWith('temp-')

      if (isPendingMessage) {
        // Pending message never reached the server - resend it
        const content = (message.contents[0] as { content: string })?.content
        if (!content) {
          throw new Error('No content found in pending message')
        }
        // addMessage returns Conversation, but we just need to know it succeeded
        await addMessage(conversationId, content)
        return { isPending: true }
      } else {
        // Real message - use the retry API which returns the updated Message
        const updatedMessage = await retryMessage(message.id, {
          title: t('target.watchFiles.chat.error.retry_failed_title'),
          description: t('target.watchFiles.chat.error.retry_failed_description'),
        })
        return { isPending: false, updatedMessage }
      }
    },
    onMutate({ message }) {
      // Update message status to pending while retrying
      conversationStore.updateMessageStatus(message.id, MessageStatus.PENDING)
      return { messageId: message.id }
    },
    onSettled(_, __, { conversationId }) {
      queryCache.invalidateQueries({
        key: CONVERSATION_QUERY_KEYS.messages(conversationId),
      })
    },
    onSuccess(result, { message }) {
      if (result.isPending) {
        // Remove the old pending message - the server will send the real one via Mercure
        conversationStore.removeMessage(message.id)
      } else if (result.updatedMessage) {
        // Update the existing message with server response
        conversationStore.addOrUpdateMessage(result.updatedMessage)
      }
    },
    onError(error, _, context) {
      if (context?.messageId) {
        conversationStore.updateMessageStatus(context.messageId, MessageStatus.ERROR)
      }
      conversationStore.setWaitingForAI(false)
      toast.error(
        t('target.watchFiles.chat.error.retry_failed_title'),
        t('target.watchFiles.chat.error.retry_failed_description'),
      )
      console.error('Failed to retry message:', error)
    },
  })
  return { ...mutation, retryMessage: mutate }
})

export const useCancelConversation = defineMutation(() => {
  const toast = useToast()
  const { t } = useI18n()

  const { mutate, ...mutation } = useMutation({
    mutation: (conversationId: string) => cancelConversation(conversationId),
    onError(error) {
      toast.error(
        t('target.watchFiles.chat.cancel.error_title'),
        t('target.watchFiles.chat.cancel.error_description'),
      )
      console.error('Failed to cancel conversation:', error)
    },
  })
  return { ...mutation, cancelConversation: mutate }
})

export const useGetOlderConversationMessages = defineMutation(() => ({
  mutation: (nextUrl: string) => {
    return getOlderConversationMessages(nextUrl)
  },
}))
