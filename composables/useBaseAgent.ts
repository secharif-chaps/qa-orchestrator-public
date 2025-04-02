import { Mistral } from '@mistralai/mistralai';

// Retry helper function
const withRetry = async <T>(
  operation: () => Promise<T>,
  options: {
    maxRetries?: number;
    baseDelay?: number;
    onRetry?: (attempt: number, delay: number) => void;
  } = {}
): Promise<T> => {
  const {
    maxRetries = 3,
    baseDelay = 1000,
    onRetry = (attempt, delay) => console.log(`Retrying in ${delay}ms... (attempt ${attempt}/${maxRetries})`)
  } = options;

  const sleep = (ms: number) => new Promise(resolve => setTimeout(resolve, ms));

  let lastError: any;
  for (let attempt = 0; attempt <= maxRetries; attempt++) {
    try {
      return await operation();
    } catch (error: any) {
      lastError = error;
      if (error.message?.includes('rate limit exceeded') && attempt < maxRetries) {
        const delay = baseDelay * Math.pow(2, attempt);
        onRetry(attempt + 1, delay);
        await sleep(delay);
        continue;
      }
      throw error;
    }
  }
  throw lastError;
};

export const useBaseAgent = () => {
  const runtimeConfig = useRuntimeConfig();
  const companyStore = useCompanyStore();
  
  // Create Mistral client
  const client = new Mistral({ apiKey: runtimeConfig.public.mistralApiKey });
  
  // Common agent IDs
  const agentIds = {
    sourced: runtimeConfig.public.mistralAgentSourced,
    chat: runtimeConfig.public.mistralAgentChat,
    timeline: runtimeConfig.public.mistralAgentTimeline,
    products: runtimeConfig.public.mistralAgentProducts,
    jobs: runtimeConfig.public.mistralAgentJobs
  };
  
  // Streaming mode toggle
  const useStreaming = ref(true);
  
  /**
   * Toggle between streaming and classic modes
   */
  const toggleStreamingMode = (streaming: boolean) => {
    useStreaming.value = streaming;
  };
  
  return {
    client,
    agentIds,
    withRetry,
    companyStore,
    useStreaming,
    toggleStreamingMode
  };
}; 