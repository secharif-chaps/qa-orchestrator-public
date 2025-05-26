import { defineStore } from 'pinia';

interface AgentState {
  pending_states: {
    profile: boolean;
    timeline: boolean;
    products: boolean;
    jobOffers: boolean;
  };
}

export const useAgentStore = defineStore('agent', {
  state: (): AgentState => ({
    pending_states: {
      profile: false,
      timeline: false,
      products: false,
      jobOffers: false
    }
  }),

  actions: {
    setPendingState(agent: keyof AgentState['pending_states'], value: boolean) {
      this.pending_states[agent] = value;
    },

    getPendingState(agent: keyof AgentState['pending_states']): boolean {
      return this.pending_states[agent];
    }
  }
}); 