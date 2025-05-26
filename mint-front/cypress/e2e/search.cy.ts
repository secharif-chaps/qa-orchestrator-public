import { mockMistralCalls, waitForMistralCalls, mockMistralError } from '../support/mistral-mocks'

describe('Company Search', () => {
  beforeEach(() => {
    // Handle uncaught exceptions from the application
    cy.on('uncaught:exception', (err, runnable) => {
      // returning false here prevents Cypress from failing the test
      return false
    })

    // Set up all Mistral API mocks
    mockMistralCalls()
    
    cy.visit('/search')
  })

  it('should display the company search form', () => {
    // Verify page container
    cy.get('[data-cy="company-search-page"]').should('exist')
    
    // Verify page title
    cy.get('h1').should('contain', 'New company')
    
    // Verify form fields
    cy.get('[data-cy="company-name-input"]').should('exist')
    cy.get('[data-cy="website-input"]').should('exist')
    
    // Verify buttons
    cy.get('[data-cy="delete-data-button"]').should('exist')
    cy.get('[data-cy="launch-search-button"]').should('exist')
  })

  describe('Form Validation', () => {
    it('should show error for invalid company name (too short)', () => {
      cy.get('[data-cy="company-name-input"]').type('a')
      cy.get('[data-cy="company-name-input"]').blur()
      cy.get('[data-cy="launch-search-button"]').should('be.disabled')
    })

    it('should show error for invalid website URL', () => {
      cy.get('[data-cy="website-input"]').type('not-a-url')
      cy.get('[data-cy="website-input"]').blur()
      cy.get('[data-cy="launch-search-button"]').should('be.disabled')
    })

    it('should show error for website URL without protocol', () => {
      cy.get('[data-cy="website-input"]').type('www.example.com')
      cy.get('[data-cy="website-input"]').blur()
      cy.get('[data-cy="launch-search-button"]').should('be.disabled')
    })

    it('should clear errors when user starts typing again', () => {
      // First trigger an error
      cy.get('[data-cy="company-name-input"]').type('a')
      cy.get('[data-cy="company-name-input"]').blur()
      
      // Then start typing again
      cy.get('[data-cy="company-name-input"]').type('Valid Company')
      cy.get('[data-cy="launch-search-button"]').should('not.be.disabled')
    })
  })

  describe('Form Reset', () => {
    it('should clear all form fields and errors when clicking reset', () => {
      // Fill in the form with invalid data
      cy.get('[data-cy="company-name-input"]').type('a')
      cy.get('[data-cy="website-input"]').type('not-a-url')
      
      // Click reset button
      cy.get('[data-cy="delete-data-button"]').click()
      
      // Verify fields are cleared
      cy.get('[data-cy="company-name-input"]').should('have.value', '')
      cy.get('[data-cy="website-input"]').should('have.value', '')
      
      // Verify no errors are shown
      cy.get('[data-cy="launch-search-button"]').should('not.be.disabled')
    })
  })

  describe('Successful Search', () => {
    it('should perform search with valid data', () => {
      const validCompany = 'Sephora'
      const validWebsite = 'https://www.sephora.fr'
      
      cy.get('[data-cy="company-name-input"]').type(validCompany)
      cy.get('[data-cy="website-input"]').type(validWebsite)
      
      // Click the button and wait for navigation
      cy.get('[data-cy="launch-search-button"]').click()
      
      // Wait for all API calls to complete
      waitForMistralCalls()
      
      // Then verify navigation
      cy.url().should('include', `/cards/${validCompany}`)
    })

    it('should handle whitespace in company name', () => {
      const companyWithSpaces = '  Sephora  '
      const validWebsite = 'https://www.sephora.fr'
      
      cy.get('[data-cy="company-name-input"]').type(companyWithSpaces)
      cy.get('[data-cy="website-input"]').type(validWebsite)
      
      // Click the button and wait for navigation
      cy.get('[data-cy="launch-search-button"]').click()
      
      // Wait for all API calls to complete
      waitForMistralCalls()
      
      // Then verify navigation
      cy.url().should('include', '/cards/Sephora')
    })
  })

  describe('Error Handling', () => {
    it('should handle API errors gracefully', () => {
      // Mock an error response for the agent call
      mockMistralError('agent', 500)
      
      const validCompany = 'Sephora'
      const validWebsite = 'https://www.sephora.fr'
      
      cy.get('[data-cy="company-name-input"]').type(validCompany)
      cy.get('[data-cy="website-input"]').type(validWebsite)
      
      // Store the button reference before clicking
      cy.get('[data-cy="launch-search-button"]').as('searchButton')
      
      // Click the button
      cy.get('@searchButton').click()
      
      // Wait for the error response
      cy.wait('@agentCall')
      
      // Verify error state
      cy.get('[data-cy="error-message"]').should('exist')
      cy.get('[data-cy="launch-search-button"]').should('not.be.disabled')
    })
  })
}) 