/**
 * TAR-1448 Simple Test — Test d'accessibilité sans login
 *
 * Teste l'interface sans avoir besoin de passer par Keycloak
 * (Idéal pour vérifier si le composant est au moins rendu)
 */

import { expect, test, type Page } from '@playwright/test'

const BASE_URL = 'http://localhost'

test('TAR-1448 — Vérifications accessibilité page watchfiles (pas de login requis)', async ({
  page,
}) => {
  await page.goto(`${BASE_URL}/watchfiles`, {
    waitUntil: 'load',
    timeout: 45_000,
  })

  // Wait for page to fully load
  await page.waitForLoadState('networkidle', { timeout: 20_000 }).catch(() => {})

  console.log('✓ Page watchfiles chargée')

  // Take screenshot to inspect the page
  await page.screenshot({ path: '/tmp/watchfiles-page.png' })
  console.log('✓ Screenshot sauvegardé: /tmp/watchfiles-page.png')

  // Get page title to verify we're on the right page
  const title = await page.title()
  console.log(`  Titre de la page: "${title}"`)

  // Try to find search input
  const searchInputs = await page.locator('input[type="text"], input[type="search"]').count()
  console.log(`  Champs de recherche trouvés: ${searchInputs}`)

  // Try to find filter button
  const buttons = await page.locator('button').count()
  console.log(`  Boutons trouvés: ${buttons}`)

  // Try to find a filter-like button
  const filterButtons = await page
    .locator('button')
    .filter({ hasText: /filter|filtres?/i })
    .count()
  console.log(`  Boutons "filtre" trouvés: ${filterButtons}`)

  // Check if we're redirected to login
  if (page.url().includes('auth') || page.url().includes('login')) {
    console.log('⚠ Redirection vers authentification détectée')
    console.log(`  URL actuelle: ${page.url()}`)

    // Try to check if login form exists
    const usernameField = await page.locator('input[name="username"], input[id="username"]').isVisible()
    console.log(`  Formulaire de login visible: ${usernameField}`)
  } else {
    console.log('✓ Pas de redirection vers l\'authentification')
    console.log(`  URL: ${page.url()}`)
  }

  // Get page content for inspection
  const bodyText = await page.textContent('body')
  const hasWatchfiles = bodyText?.toLowerCase().includes('watchfile') || bodyText?.toLowerCase().includes('dossier')
  const hasError = bodyText?.toLowerCase().includes('error') || bodyText?.toLowerCase().includes('erreur')

  console.log(`  Page contient "watchfile" ou "dossier": ${hasWatchfiles}`)
  console.log(`  Erreurs détectées: ${hasError}`)

  // List all visible text from headers (h1, h2, h3)
  const headers = await page.locator('h1, h2, h3').count()
  console.log(`  En-têtes trouvés: ${headers}`)

  if (headers > 0) {
    const firstHeader = await page.locator('h1, h2, h3').first().textContent()
    console.log(`  Premier en-tête: "${firstHeader?.trim()}"`)
  }

  console.log('\n✓ Diagnostic complet')
})

test('TAR-1448 — Manuel: Accès avec authentification via API', async ({ page, request }) => {
  // Get token via API instead of UI login
  const KEYCLOAK_TOKEN_URL =
    'http://localhost:8080/realms/chapsmind/protocol/openid-connect/token'
  const KEYCLOAK_CLIENT_ID = 'chapsmind-front'

  try {
    const tokenRes = await request.post(KEYCLOAK_TOKEN_URL, {
      form: {
        grant_type: 'password',
        client_id: KEYCLOAK_CLIENT_ID,
        username: 'admin',
        password: 'admin123',
      },
    })

    const tokenData = await tokenRes.json()
    const accessToken = (tokenData as any).access_token

    if (!accessToken) {
      console.log('⚠ Impossible d\'obtenir un token')
      return
    }

    console.log('✓ Token obtenu avec succès')

    // Set auth cookie
    await page.goto(`${BASE_URL}/watchfiles`)

    // Set the auth header
    await page.evaluate((token) => {
      localStorage.setItem('auth_token', token)
    }, accessToken)

    // Reload page with auth
    await page.reload({ waitUntil: 'networkidle' })

    console.log('✓ Page rechargée avec authentification')
    console.log(`  URL: ${page.url()}`)

    // Now check for TAR-1448 components
    const searchBar = page.locator('input[placeholder*="search" i]').first()
    const filterBtn = page.locator('button').filter({ hasText: /filter|filtres?/i }).first()

    const searchVisible = await searchBar.isVisible().catch(() => false)
    const filterVisible = await filterBtn.isVisible().catch(() => false)

    console.log(`  Barre de recherche visible: ${searchVisible}`)
    console.log(`  Bouton filtres visible: ${filterVisible}`)

    if (searchVisible || filterVisible) {
      console.log('✓ TAR-1448 composants détectés!')
    } else {
      console.log('⚠ TAR-1448 composants non visibles')
    }

    // Take screenshot
    await page.screenshot({ path: '/tmp/watchfiles-auth.png' })
    console.log('✓ Screenshot sauvegardé: /tmp/watchfiles-auth.png')
  } catch (error) {
    console.log(`⚠ Erreur lors du test d'authentification API:`, error)
  }
})
