import { defineStore } from 'pinia';

interface AgentState {
  pendingStates: {
    profile: boolean;
    timeline: boolean;
    products: boolean;
    jobOffers: boolean;
  };
}

export const useAgentStore = defineStore('agent', {
  state: (): AgentState => ({
    pendingStates: {
      profile: false,
      timeline: false,
      products: false,
      jobOffers: false
    }
  }),

  actions: {
    setPendingState(agent: keyof AgentState['pendingStates'], value: boolean) {
      this.pendingStates[agent] = value;
    },

    getPendingState(agent: keyof AgentState['pendingStates']): boolean {
      return this.pendingStates[agent];
    }
  }
}); 