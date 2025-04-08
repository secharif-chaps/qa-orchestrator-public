export const useProductsAgent = () => {
  const { client, agentIds, withRetry, companyStore } = useBaseAgent();
  const agentStore = useAgentStore();

  /**
   * Finds and lists all products and services offered by a company
   */
  const findProducts = async (company: string, onChunk?: (text: string) => void) => {
    agentStore.setPendingState('products', true);
    
    try {
      await withRetry(
        async () => {
          const prompt = `Find and list all products and services offered by ${company}.`;
          const response = await client.agents.stream({
            messages: [{ role: 'user', content: prompt }],
            stream: true,
            agentId: agentIds.products,
            responseFormat: { type: 'json_object' }
          });

          let accumulated = '';
          for await (const chunk of response) {
            const content = chunk.data.choices[0]?.delta?.content || '';
            accumulated += content;
            
            if (onChunk) {
              onChunk(content as string);
            }
          }

          const parsedData = JSON.parse(accumulated);
          if (parsedData.products) {
            companyStore.updateCompanyProperty(company, 'products', parsedData.products);
          }
        },
        {
          onRetry: (attempt, delay) => console.log(`Products rate limit hit, retrying in ${delay}ms... (attempt ${attempt}/3)`)
        }
      );
    } catch (error) {
      console.error('Error finding products:', error);
      throw error;
    } finally {
      agentStore.setPendingState('products', false);
    }
  };

  return {
    findProducts,
    productsPending: computed(() => agentStore.getPendingState('products'))
  };
}; 