export const useAgent = () => {
  // Import all specialized agents
  const baseAgent = useBaseAgent();
  const profileAgent = useCompanyProfileAgent();
  const timelineAgent = useTimelineAgent();
  const productsAgent = useProductsAgent();
  const jobOffersAgent = useJobOffersAgent();
  const chatAgent = useChatAgent();
  
  // Return a unified interface that matches the original useAgent API
  return {
    // Company profile functionality
    findProfile: profileAgent.generate,
    profilePending: profileAgent.profilePending,
    companyName: profileAgent.companyName,
    
    // Timeline functionality
    findTimeline: timelineAgent.generateTimeline,
    timelinePending: timelineAgent.timelinePending,
    
    // Products functionality
    findProducts: productsAgent.findProducts,
    productsPending: productsAgent.productsPending,

    // Job offers functionality
    findJobOffers: jobOffersAgent.findJobOffers,
    jobOffersPending: jobOffersAgent.jobOffersPending,
    
    // Chat functionality
    ask: chatAgent.ask,
    
    // Streaming mode toggle
    useStreaming: baseAgent.useStreaming,
    toggleStreamingMode: baseAgent.toggleStreamingMode
  };
};
