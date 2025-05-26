// Mock intercepts for Mistral API calls
export const mockMistralCalls = () => {
  // Mock agent completion call for company profile
  cy.intercept('POST', '**/agents/completions', (req) => {
    if (req.body.agent_id === Cypress.env('NUXT_PUBLIC_MISTRAL_AGENT_SOURCED')) {
      req.reply({
        statusCode: 200,
        body: require('../fixtures/mistral/agent-completion.json')
      })
    }
  }).as('agentCall')

  // Mock agent completion call for timeline
  cy.intercept('POST', '**/agents/completions', (req) => {
    if (req.body.agent_id === Cypress.env('NUXT_PUBLIC_MISTRAL_AGENT_TIMELINE')) {
      req.reply({
        statusCode: 200,
        body: require('../fixtures/mistral/timeline.json')
      })
    }
  }).as('timelineCall')

  // Mock agent completion call for products
  cy.intercept('POST', '**/agents/completions', (req) => {
    if (req.body.agent_id === Cypress.env('NUXT_PUBLIC_MISTRAL_AGENT_PRODUCTS')) {
      req.reply({
        statusCode: 200,
        body: require('../fixtures/mistral/products.json')
      })
    }
  }).as('productsCall')
}

// Helper to wait for all Mistral API calls to complete
export const waitForMistralCalls = () => {
  cy.wait(['@agentCall', '@timelineCall', '@productsCall'])
}

// Helper to mock error responses
export const mockMistralError = (endpoint: 'agent' | 'timeline' | 'products', statusCode: number = 500) => {
  const agentIds = {
    agent: Cypress.env('NUXT_PUBLIC_MISTRAL_AGENT_SOURCED'),
    timeline: Cypress.env('NUXT_PUBLIC_MISTRAL_AGENT_TIMELINE'),
    products: Cypress.env('NUXT_PUBLIC_MISTRAL_AGENT_PRODUCTS')
  }

  cy.intercept('POST', '**/agents/completions', (req) => {
    if (req.body.agent_id === agentIds[endpoint]) {
      req.reply({
        statusCode,
        body: {
          error: 'Mock error response',
          message: 'This is a mock error for testing'
        }
      })
    }
  }).as(`${endpoint}Call`)
} 