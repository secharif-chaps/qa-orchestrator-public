export const useAgent = () => {
  // Import all specialized agents
  const baseAgent = useBaseAgent();
  const profileAgent = useCompanyProfileAgent();
  const timelineAgent = useTimelineAgent();
  const productsAgent = useProductsAgent();
  const jobOffersAgent = useJobOffersAgent();
  const chatAgent = useChatAgent();
  const teamAgent = useTeamAgent();
  // Return a unified interface that matches the original useAgent API
  return {
    // Company profile functionality
    findProfile: profileAgent.generate,
    companyName: profileAgent.companyName,
    
    // Timeline functionality
    findTimeline: timelineAgent.generateTimeline,
    
    // Products functionality
    findProducts: productsAgent.findProducts,

    // Job offers functionality
    findJobOffers: jobOffersAgent.findJobOffers,

    // Team functionality
    findTeam: teamAgent.findTeamHierarchy,
    
    // Chat functionality
    ask: chatAgent.ask,
    
    // Streaming mode toggle
    useStreaming: baseAgent.useStreaming,
    toggleStreamingMode: baseAgent.toggleStreamingMode
  };
};
