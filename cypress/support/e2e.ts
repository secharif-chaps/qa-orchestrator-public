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

// Declare the type for the custom command
declare global {
  namespace Cypress {
    interface Chainable {
      login(email: string, password: string): Chainable<void>
    }
  }
} 