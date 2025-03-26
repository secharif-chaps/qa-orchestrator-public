export const useJobOffersAgent = () => {
  const { client, agentIds, withRetry, companyStore } = useBaseAgent();
  
  const jobOffersPending = ref(false);
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
    jobOffersPending.value = true;
    let accumulatedJson = '';
    // Clear the processed jobs set when starting a new search
    processedJobTitles.clear();
    
    // Initialize empty job offers array in store
    companyStore.updateCompanyProperty(company, 'job_offers', []);
    
    try {
      await withRetry(
        async () => {
          const prompt = `Research and analyze current job offers for ${company}. Include both the specific job listings and provide strategic insights about what these openings reveal about the company's focus and growth areas.`;
          
          const response = await client.agents.stream({
            messages: [{ role: 'user', content: prompt }],
            stream: true,
            agentId: agentIds.jobs,
            responseFormat: { type: 'json_object' }
          });

          let accumulated = '';
          for await (const chunk of response) {
            const content = chunk.data.choices[0]?.delta?.content || '';
            accumulated += content;
            accumulatedJson += content;
            
            try {
              // Try to parse the accumulated JSON to find complete job offers
              const partialData = extractPartialData(accumulated);
              if (partialData) {
                // Handle new job offers
                if (partialData.job_offers?.length > 0) {
                  const currentOffers = companyStore.getCompanyByName(company)?.job_offers || [];
                  const newOffers = partialData.job_offers.filter(job => {
                    const title = job.title?.value;
                    if (!title || processedJobTitles.has(title)) return false;
                    processedJobTitles.add(title);
                    return true;
                  });

                  if (newOffers.length > 0) {
                    // Add each new offer individually with a small delay
                    for (const offer of newOffers) {
                      companyStore.updateCompanyProperty(company, 'job_offers', [...currentOffers, offer]);
                      // Small delay to ensure visual separation
                      await new Promise(resolve => setTimeout(resolve, 300));
                    }
                  }
                }
                
                // Update insights if available
                if (partialData.insights) {
                  companyStore.updateCompanyProperty(company, 'job_offers_insights', partialData.insights);
                }
              }
            } catch (e) {
              // Continue accumulating if we can't parse yet
              continue;
            }
          }

          // Final parse to ensure we didn't miss anything
          try {
            const finalData = JSON.parse(accumulatedJson);
            if (finalData.job_offers) {
              const currentOffers = companyStore.getCompanyByName(company)?.job_offers || [];
              const finalOffers = finalData.job_offers.filter(job => {
                const title = job.title?.value;
                if (!title || processedJobTitles.has(title)) return false;
                processedJobTitles.add(title);
                return true;
              });

              if (finalOffers.length > 0) {
                // Add remaining offers individually
                for (const offer of finalOffers) {
                  companyStore.updateCompanyProperty(company, 'job_offers', [...currentOffers, offer]);
                  await new Promise(resolve => setTimeout(resolve, 300));
                }
              }
            }
            if (finalData.insights) {
              companyStore.updateCompanyProperty(company, 'job_offers_insights', finalData.insights);
            }
          } catch (e) {
            console.error('Error parsing final job offers data:', e);
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
      jobOffersPending.value = false;
      // Clear the processed jobs set when done
      processedJobTitles.clear();
    }
  };

  return {
    findJobOffers,
    jobOffersPending
  };
}; 