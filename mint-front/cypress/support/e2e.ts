// Import commands.js using ES2015 syntax:
import './commands'

// Alternatively you can use CommonJS syntax:
// require('./commands')

// Hide XHR requests from command log
const app = window.top;
if (app) {
  app.console.log = () => {};
}

// You can add custom commands here
Cypress.Commands.add('login', (email: string, password: string) => {
  // Add your login command implementation here
  // This is just a placeholder - you'll need to implement the actual login logic
  cy.visit('/login')
  cy.get('[data-cy="email-input"]').type(email)
  cy.get('[data-cy="password-input"]').type(password)
  cy.get('[data-cy="login-button"]').click()
})

// Custom command to check if text is translated
Cypress.Commands.add('shouldBeTranslated', (selector: string, expectedText: string) => {
  cy.get(selector).should('contain', expectedText)
})

// Custom command to switch language
Cypress.Commands.add('switchLanguage', (locale: string) => {
  cy.get('#locale-select').select(locale)
  // Wait for translations to be applied
  cy.wait(100)
})

// Custom command to check if element is translated
Cypress.Commands.add('isTranslated', (selector: string, originalText: string) => {
  cy.get(selector).should('not.contain', originalText)
})

// Declare the types for the custom commands
declare global {
  namespace Cypress {
    interface Chainable {
      login(email: string, password: string): Chainable<void>
      shouldBeTranslated(selector: string, expectedText: string): Chainable<void>
      switchLanguage(locale: string): Chainable<void>
      isTranslated(selector: string, originalText: string): Chainable<void>
    }
  }
} 