export const useTimelineAgent = () => {
  const { client, agentIds, withRetry, companyStore } = useBaseAgent();
  
  const timelinePending = ref(false);

  /**
   * Generates a timeline of key milestone events for a company
   */
  const generateTimeline = async (company: string) => {
    const prompt = `Research and provide a timeline of key milestone events for the company ${company}.`;
    
    timelinePending.value = true;
    console.log('Timeline generation started, timelinePending:', timelinePending.value);
    
    try {
      const result = await withRetry(
        async () => {
          const response = await client.agents.complete({
            agentId: agentIds.timeline,
            messages: [{ role: 'user', content: prompt }],
            responseFormat: { type: 'json_object' }
          });

          if (!response.choices?.length) {
            return {};
          }

          const content = response.choices[0].message.content?.toString() || '';
          const parsedResult = JSON.parse(content);
          
          if (parsedResult.timeline_events && Array.isArray(parsedResult.timeline_events)) {
            companyStore.updateCompanyProperty(company, 'timeline_events', parsedResult.timeline_events);
            
            if (parsedResult.meta?.query_date) {
              companyStore.updateCompanyProperty(company, 'meta.query_date', parsedResult.meta.query_date);
            }
          }
          
          return parsedResult;
        },
        {
          onRetry: (attempt, delay) => console.log(`Timeline rate limit hit, retrying in ${delay}ms... (attempt ${attempt}/3)`)
        }
      );

      timelinePending.value = false;
      console.log('Timeline generation completed, timelinePending:', timelinePending.value);
      return result;
    } catch (error) {
      console.error('Error during timeline data request:', error);
      timelinePending.value = false;
      console.log('Timeline generation failed (request error), timelinePending:', timelinePending.value);
      return {};
    }
  };

  return {
    generateTimeline,
    timelinePending
  };
}; 