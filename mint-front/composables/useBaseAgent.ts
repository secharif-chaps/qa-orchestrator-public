import { Mistral } from '@mistralai/mistralai'

// Retry helper function
const withRetry = async <T>(
  operation: () => Promise<T>,
  options: {
    maxRetries?: number
    baseDelay?: number
    maxDelay?: number
    jitter?: boolean
    onRetry?: (attempt: number, delay: number, error: any) => void
    shouldRetry?: (error: any) => boolean
  } = {}
): Promise<T> => {
  const {
    maxRetries = 3,
    baseDelay = 1000,
    maxDelay = 30000, // 30 seconds max delay
    jitter = true,
    onRetry = (attempt, delay, error) =>
      console.log(`Retrying in ${delay}ms... (attempt ${attempt}/${maxRetries})`),
    shouldRetry = error => {
      // Retry on 429 (Too Many Requests) or network errors
      return (
        error?.status === 429 ||
        error?.message?.includes('rate limit exceeded') ||
        error?.message?.includes('network error') ||
        error?.message?.includes('timeout')
      )
    }
  } = options

  const sleep = (ms: number) => new Promise(resolve => setTimeout(resolve, ms))

  let lastError: any
  for (let attempt = 0; attempt <= maxRetries; attempt++) {
    try {
      return await operation()
    } catch (error: any) {
      lastError = error

      // Check if we should retry based on the error
      if (shouldRetry(error) && attempt < maxRetries) {
        // Calculate delay with exponential backoff and optional jitter
        let delay = baseDelay * Math.pow(2, attempt)

        // Add jitter (random variation) to prevent thundering herd
        if (jitter) {
          const jitterAmount = Math.random() * 0.1 * delay // 10% jitter
          delay += jitterAmount
        }

        // Cap the delay at maxDelay
        delay = Math.min(delay, maxDelay)

        onRetry(attempt + 1, delay, error)
        await sleep(delay)
        continue
      }
      throw error
    }
  }
  throw lastError
}

export const useBaseAgent = () => {
  const runtimeConfig = useRuntimeConfig()

  // Create Mistral client
  const client = new Mistral({ apiKey: runtimeConfig.public.mistralApiKey })

  // Common agent IDs
  const agentIds = {
    sourced: runtimeConfig.public.mistralAgentSourced,
    chat: runtimeConfig.public.mistralAgentChat,
    timeline: runtimeConfig.public.mistralAgentTimeline,
    products: runtimeConfig.public.mistralAgentProducts,
    jobs: runtimeConfig.public.mistralAgentJobs,
    team: runtimeConfig.public.mistralAgentTeam
  }

  // Streaming mode toggle
  const useStreaming = ref(true)

  /**
   * Toggle between streaming and classic modes
   */
  const toggleStreamingMode = (streaming: boolean) => {
    useStreaming.value = streaming
  }

  return {
    client,
    agentIds,
    withRetry,
    useStreaming,
    toggleStreamingMode
  }
}
