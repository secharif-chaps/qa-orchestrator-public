export const useJobOffersAgent = () => {
  const { client, agentIds, withRetry, companyStore } = useBaseAgent();
  const agentStore = useAgentStore();
  
  // Keep track of processed job offers to avoid duplicates
  const processedJobTitles = new Set<string>();

  /**
   * Attempts to extract individual job offers and insights from a partial JSON string
   */
  const extractPartialData = (json: string): { job_offers?: any[], insights?: any } | null => {
    try {
      let extractedData: { job_offers?: any[], insights?: any } = {};
      
      // Look for individual job offer objects
      const jobObjectRegex = /\{[^{]*"title"\s*:\s*\{[^}]*\}[^}]*\}/g;
      const jobMatches = [...json.matchAll(jobObjectRegex)];
      
      if (jobMatches.length > 0) {
        extractedData.job_offers = [];
        for (const match of jobMatches) {
          try {
            const jobObject = JSON.parse(match[0]);
            // Verify this is a valid job offer object
            if (jobObject.title?.value && 
                jobObject.location?.value && 
                jobObject.department?.value) {
              extractedData.job_offers.push(jobObject);
            }
          } catch (e) {
            // Skip invalid job objects
            continue;
          }
        }
      }

      // Try to find insights object
      const insightsMatch = json.match(/"insights"\s*:\s*(\{[^}]+\})/);
      if (insightsMatch) {
        try {
          const insightsJson = `{"insights":${insightsMatch[1]}}`;
          const parsed = JSON.parse(insightsJson);
          if (parsed.insights) {
            extractedData.insights = parsed.insights;
          }
        } catch (e) {
          // Ignore parsing errors for incomplete insights
        }
      }

      return Object.keys(extractedData).length > 0 ? extractedData : null;
    } catch (e) {
      return null;
    }
  };

  /**
   * Finds and analyzes job offers for a company
   */
  const findJobOffers = async (company: string) => {
    agentStore.setPendingState('jobOffers', true);
    // Clear the processed jobs set when starting a new search
    processedJobTitles.clear();
    
    // Initialize empty job offers array in store
    companyStore.updateCompanyProperty(company, 'job_offers', []);
    
    try {
      await withRetry(
        async () => {
          const prompt = `Research and analyze current job offers for ${company}. Include both the specific job listings and provide strategic insights about what these openings reveal about the company's focus and growth areas.`;
          
          const response = await client.agents.complete({
            messages: [{ role: 'user', content: prompt }],
            agentId: agentIds.jobs,
            responseFormat: { type: 'json_object' }
          });

          const data = JSON.parse(response.choices[0].message.content);
          
          if (data.job_offers) {
            const currentOffers = companyStore.getCompanyByName(company)?.job_offers || [];
            const newOffers = data.job_offers.filter(job => {
              const title = job.title?.value;
              if (!title || processedJobTitles.has(title)) return false;
              processedJobTitles.add(title);
              return true;
            });

            if (newOffers.length > 0) {
              companyStore.updateCompanyProperty(company, 'job_offers', [...currentOffers, ...newOffers]);
            }
          }

          if (data.insights) {
            companyStore.updateCompanyProperty(company, 'job_offers_insights', data.insights);
          }
        },
        {
          onRetry: (attempt, delay) => console.log(`Job offers rate limit hit, retrying in ${delay}ms... (attempt ${attempt}/3)`)
        }
      );
    } catch (error) {
      console.error('Error finding job offers:', error);
      throw error;
    } finally {
      agentStore.setPendingState('jobOffers', false);
      // Clear the processed jobs set when done
      processedJobTitles.clear();
    }
  };

  return {
    findJobOffers,
    jobOffersPending: computed(() => agentStore.getPendingState('jobOffers'))
  };
}; 