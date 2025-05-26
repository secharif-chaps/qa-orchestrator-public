export const useChatAgent = () => {
  const { client, agentIds } = useBaseAgent()
  const companyStore = useCompanyStore()
  /**
   * Asks a question to the chat agent about a company
   */
  const ask = async (
    question: string,
    companyName: string,
    context: any,
    onChunk: (text: string) => void
  ) => {
    const company = companyStore.companies[companyName]

    try {
      const response = await client.agents.stream({
        agentId: agentIds.chat,
        messages: [
          {
            role: 'system',
            content: `Voici les informations de l'entreprise : ${JSON.stringify(company)}`
          },
          {
            role: 'system',
            content: `Voici le context des précedants messages : ${JSON.stringify(context)}`
          },
          {
            role: 'user',
            content: question
          }
        ],
        stream: true // Enable streaming
      })

      let fullResponse = ''

      // Handle the stream
      if (response) {
        for await (const chunk of response) {
          if (chunk.data.choices && chunk.data.choices[0]?.delta?.content) {
            const contentChunk = chunk.data.choices[0].delta.content as string
            fullResponse += contentChunk
            onChunk(contentChunk) // Send each chunk to the callback
          }
        }
      }

      return fullResponse
    } catch (error) {
      console.error('Error with agent response:', error)
      return null
    }
  }

  return {
    ask
  }
}
