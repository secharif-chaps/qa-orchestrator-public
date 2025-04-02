describe('Login Page', () => {
  beforeEach(() => {
    cy.visit('/login')
  })

  it('should display the login form', () => {
    cy.get('[data-testid="email-input"]').should('be.visible')
    cy.get('[data-testid="password-input"]').should('be.visible')
    cy.get('[data-testid="submit-button"]').should('be.visible')
  })

  it('should successfully login with valid credentials', () => {
    cy.get('[data-testid="email-input"]').type('test@example.com')
    cy.get('[data-testid="password-input"]').type('password123')
    cy.get('[data-testid="submit-button"]').click()

    // Verify loading state
    cy.get('[data-testid="submit-button"]').should('contain', 'Signing in...')
    
    // After successful login, should redirect to home page
    cy.url().should('eq', Cypress.config().baseUrl + '/')
  })

  it('should show error message with invalid credentials', () => {
    cy.get('[data-testid="email-input"]').type('wrong@example.com')
    cy.get('[data-testid="password-input"]').type('wrongpassword')
    cy.get('[data-testid="submit-button"]').click()

    cy.get('[data-testid="error-message"]')
      .should('be.visible')
      .and('contain', 'Invalid email or password')
  })

  it('should validate required fields', () => {
    // Try to submit without entering any credentials
    cy.get('[data-testid="submit-button"]').click()

    // Check HTML5 validation messages
    cy.get('[data-testid="email-input"]').then($el => {
      expect($el[0].validationMessage).to.not.be.empty
    })
    cy.get('[data-testid="password-input"]').then($el => {
      expect($el[0].validationMessage).to.not.be.empty
    })
  })

  it('should validate email format', () => {
    cy.get('[data-testid="email-input"]').type('invalid-email')
    cy.get('[data-testid="password-input"]').type('password123')
    cy.get('[data-testid="submit-button"]').click()

    // Check HTML5 validation message for invalid email
    cy.get('[data-testid="email-input"]').then($el => {
      expect($el[0].validationMessage).to.not.be.empty
    })
  })

  it('should disable submit button during form submission', () => {
    cy.get('[data-testid="email-input"]').type('test@example.com')
    cy.get('[data-testid="password-input"]').type('password123')
    cy.get('[data-testid="submit-button"]').click()

    // Verify button is disabled during submission
    cy.get('[data-testid="submit-button"]').should('be.disabled')
  })
}) 