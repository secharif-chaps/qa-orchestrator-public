describe('Home Page', () => {
  beforeEach(() => {
    // Visit the home page before each test
    cy.visit('/')
  })

  it('should load the home page successfully', () => {
    // Check if the page loaded successfully
    cy.url().should('include', '/')
  })

  // Add more test cases here
  // Example:
  // it('should display the main navigation', () => {
  //   cy.get('[data-cy="main-nav"]').should('be.visible')
  // })
}) 