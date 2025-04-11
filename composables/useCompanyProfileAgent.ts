export const useCompanyProfileAgent = () => {
  const { client, agentIds, withRetry, companyStore } = useBaseAgent();
  const agentStore = useAgentStore();
  
  const companyName = ref<string | null>(null);

  /**
   * Generate company profile data using streaming
   */
  const generate = async (company: string, website: string) => {
    const prompt = `Extrayez les informations de base sur l'entreprise ${company} à partir de son site web ${website}.`;
    
    // Reset company name
    const lowerCaseCompany = company.toLocaleLowerCase();

    agentStore.setPendingState('profile', true);
    
    try {
      // Use the sourced agent for streaming
      const result = await client.agents.complete({
        agentId: agentIds.sourced,
        messages: [{ role: 'user', content: prompt }],
        responseFormat: { type: 'json_object' },
      });

      console.log('result unparsed', result.choices[0].message.content)

      const data = JSON.parse(result.choices[0].message.content)

      console.log('data', data)

      const insights = data.insights

      let profile = null
      let products_and_services = null
      let target_audience_and_customer_base = null  
      let digital_strategy_and_social_media = null
      let csr = null
      let recent_news = null
      let social_media = null
    

      if (insights.profile) {
        profile = insights.profile
      } else {
        profile = data.profile
      }

      // if insights have property products_and_services, use it as the products_and_services for the profile
      if (insights.products_and_services) {
        products_and_services = insights.products_and_services
      } else {
        products_and_services = data.products_and_services
      }

      if (insights.target_audience_and_customer_base) {
        target_audience_and_customer_base = insights.target_audience_and_customer_base
      } else {
        target_audience_and_customer_base = data.target_audience_and_customer_base
      } 

      if (insights.digital_strategy_and_social_media) {
        digital_strategy_and_social_media = insights.digital_strategy_and_social_media
      } else {
        digital_strategy_and_social_media = data.digital_strategy_and_social_media
      }
      
      if (insights.csr) {
        csr = insights.csr
      } else {
        csr = data.csr
      }

      if (insights.recent_news) {
        recent_news = insights.recent_news
      } else {
        recent_news = data.recent_news
      } 

      if (insights.social_media) {
        social_media = insights.social_media
      } else {
        social_media = data.social_media
      }
        
      companyStore.updateCompanyProperty(lowerCaseCompany, 'profile', profile); 
      companyStore.updateCompanyProperty(lowerCaseCompany, 'products_and_services', products_and_services);
      companyStore.updateCompanyProperty(lowerCaseCompany, 'target_audience_and_customer_base', target_audience_and_customer_base);
      companyStore.updateCompanyProperty(lowerCaseCompany, 'digital_strategy_and_social_media', digital_strategy_and_social_media);
      companyStore.updateCompanyProperty(lowerCaseCompany, 'csr', csr);
      companyStore.updateCompanyProperty(lowerCaseCompany, 'recent_news', recent_news);
      companyStore.updateCompanyProperty(lowerCaseCompany, 'social_media', social_media);

        
      agentStore.setPendingState('profile', false);

      } catch (e) {
        console.error('Failed to parse final JSON response:', e);
        agentStore.setPendingState('profile', false);
      }
  
  };

  return {
    generate,
    profilePending: computed(() => agentStore.getPendingState('profile')),
    companyName
  };
};