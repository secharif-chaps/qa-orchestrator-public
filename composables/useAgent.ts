export const useAgent = () => {
  // Import all specialized agents
  const baseAgent = useBaseAgent()
  const chatAgent = useChatAgent()
  // Return a unified interface that matches the original useAgent API
  return {
    // Chat functionality
    ask: chatAgent.ask,

    // Streaming mode toggle
    useStreaming: baseAgent.useStreaming,
    toggleStreamingMode: baseAgent.toggleStreamingMode
  }
}
