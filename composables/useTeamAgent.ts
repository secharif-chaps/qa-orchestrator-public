import { useBaseAgent } from './useBaseAgent'
import { useAgentStore } from '~/stores/agent'

export const useTeamAgent = () => {
  const { client, agentIds, withRetry, companyStore } = useBaseAgent()
  const agentStore = useAgentStore()

  const findTeamHierarchy = async (companyName: string) => {
    const operation = async () => {
      const response = await client.agents.complete({
        responseFormat: { type: 'json_object' },
        messages: [
          {
            role: 'user',
            content: `retrieve the team hierarchy for ${companyName}.`
          }
        ],
        agentId: agentIds.team
      })

      return response.choices[0].message.content
    }

    try {
      agentStore.setPendingState('team', true)
      const result = await withRetry(operation)
      const parsedResult = JSON.parse(result)
      
      // Update company store with team data
      companyStore.updateCompanyProperty(companyName, 'team', 
        parsedResult.management
      )
      
      return parsedResult
    } finally {
      agentStore.setPendingState('team', false)
    }
  }

  return {
    findTeamHierarchy
  }
} 