describe('Navigation', () => {
  beforeEach(() => {
    cy.visit('/')
  })

  it('should navigate to all main pages', () => {
    // Test navigation to Search page
    cy.get('a[href="/search"]').click()
    cy.url().should('include', '/search')
    cy.get('h1').should('contain', 'New company')

    // Test navigation to Help page
    cy.get('a[href="/help"]').click()
    cy.url().should('include', '/help')
    cy.get('h2').should('contain', 'Help')

    // Test navigation to Settings page
    cy.get('a[href="/settings"]').click()
    cy.url().should('include', '/settings')
    cy.get('h2').should('contain', 'Settings')

    // Test navigation back to Home
    cy.get('a[href="/"]').click()
    cy.url().should('eq', 'http://localhost:3000/')
  })

  it('should maintain navigation state after page refresh', () => {
    // Navigate to search page
    cy.get('a[href="/search"]').click()
    
    // Refresh the page
    cy.reload()
    
    // Verify we're still on the search page
    cy.url().should('include', '/search')
  })
}) 