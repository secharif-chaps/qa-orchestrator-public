describe('i18n Localization', () => {
  beforeEach(() => {
    // Visit the settings page where the language switcher is located
    cy.visit('/settings')
  })

  it('should display correct translations for English', () => {
    // Check English translations
    cy.get('[data-testid="language-title"]').should('contain', 'Language Settings')
    cy.get('[data-testid="language-description"]').should('contain', 'Choose your preferred language for the application')
  })

  it('should switch to French and display correct translations', () => {
    // Select French from the language dropdown
    cy.get('#locale-select').select('fr-FR')
    
    // Check French translations
    cy.get('[data-testid="language-title"]').should('contain', 'Paramètres de langue')
    cy.get('[data-testid="language-description"]').should('contain', 'Choisissez votre langue préférée pour l\'application')
  })

  it('should persist language selection in localStorage', () => {
    // Select French
    cy.get('#locale-select').select('fr-FR')
    
    // Check localStorage
    cy.window().then((win) => {
      expect(win.localStorage.getItem('user-locale')).to.equal('fr-FR')
    })
  })

  it('should update all UI elements when language changes', () => {
    // Store initial English text
    let englishTexts: string[] = []
    cy.get('[data-testid$="-title"]').each(($el) => {
      englishTexts.push($el.text())
    })

    // Switch to French
    cy.get('#locale-select').select('fr-FR')

    // Verify all texts have changed
    cy.get('[data-testid$="-title"]').each(($el, index) => {
      expect($el.text()).to.not.equal(englishTexts[index])
    })
  })

  it('should maintain language selection after page refresh', () => {
    // Select French
    cy.get('#locale-select').select('fr-FR')
    
    // Refresh the page
    cy.reload()
    
    // Verify French is still selected
    cy.get('#locale-select').should('have.value', 'fr-FR')
  })

  it('should update dynamic content translations', () => {
    // First navigate to cards page to get initial English text
    cy.visit('/cards')
    cy.get('h1').should('contain', 'Cards')
    
    // Go to settings to change language
    cy.visit('/settings')
    cy.get('#locale-select').select('fr-FR')
    
    // Navigate back to cards page to verify translation
    cy.visit('/cards')
    cy.get('h1').should('contain', 'Cartes')
  })

  it('should handle missing translations gracefully', () => {
    // Switch to French
    cy.get('#locale-select').select('fr-FR')
    
    // Navigate to different pages to check for missing translations
    cy.visit('/')
    cy.visit('/search')
    cy.visit('/help')
    
    // Verify no translation errors in console
    cy.window().then((win) => {
      const consoleErrors = win.console.error
      expect(consoleErrors).to.not.be.called
    })
  })
}) 