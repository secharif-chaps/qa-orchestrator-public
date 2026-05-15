/**
 * Internal JWT Generator for Playwright E2E Tests
 *
 * Generates HS256-signed JWT tokens that match the backend's expectations
 * (apps/global-service/app/core/internal_jwt.py).
 *
 * Usage:
 *   const jwtGen = new JWTGenerator(INTERNAL_JWT_SECRET)
 *   const token = jwtGen.createToken({
 *     userId: 'user-123',
 *     username: 'admin',
 *     orgId: 'org-456',
 *     orgName: 'Test Org',
 *     roles: ['admin'],
 *   })
 */

const crypto = require('crypto')

const ALGORITHM = 'HS256'
const ISSUER = 'global-gateway'
const DEFAULT_EXPIRY_SECONDS = 3600

class JWTGenerator {
  constructor(secret, expirySeconds = DEFAULT_EXPIRY_SECONDS) {
    if (!secret) {
      throw new Error('INTERNAL_JWT_SECRET is required. Check your .env or backend config.')
    }
    this.secret = secret
    this.expirySeconds = expirySeconds
  }

  /**
   * Create a signed internal JWT token
   * @param {Object} payload Token payload
   * @param {string} payload.userId User ID (Keycloak sub)
   * @param {string} payload.username Username
   * @param {string} payload.orgId Organization UUID
   * @param {string} payload.orgName Organization name
   * @param {string[]} payload.roles User roles
   * @param {string} [payload.email] Optional user email
   * @param {number} [expirySeconds] Token expiry (overrides constructor default)
   * @returns {string} Signed JWT token
   */
  createToken(payload, expirySeconds = this.expirySeconds) {
    const now = Math.floor(Date.now() / 1000)
    const exp = now + expirySeconds

    const tokenPayload = {
      sub: payload.userId,
      username: payload.username,
      email: payload.email || null,
      org_id: payload.orgId,
      org_name: payload.orgName,
      roles: payload.roles || [],
      iss: ISSUER,
      iat: now,
      exp,
    }

    return this._sign(tokenPayload)
  }

  /**
   * Create a token with Authorization header format
   * @returns {string} "Internal <token>" for Authorization header
   */
  createAuthHeader(payload, expirySeconds) {
    const token = this.createToken(payload, expirySeconds)
    return `Internal ${token}`
  }

  /**
   * Decode a token (without verification) for inspection
   */
  decodeToken(token) {
    try {
      const parts = token.split('.')
      if (parts.length !== 3) {
        throw new Error('Invalid JWT format')
      }
      const payload = JSON.parse(this._base64UrlDecode(parts[1]))
      return payload
    } catch (err) {
      throw new Error(`Failed to decode token: ${err.message}`)
    }
  }

  /**
   * Check if a token is expired
   */
  isTokenExpired(token) {
    try {
      const payload = this.decodeToken(token)
      return payload.exp < Math.floor(Date.now() / 1000)
    } catch {
      return true
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Private Methods
  // ─────────────────────────────────────────────────────────────────────────

  _sign(payload) {
    const header = { alg: ALGORITHM, typ: 'JWT' }
    const headerB64 = this._base64UrlEncode(JSON.stringify(header))
    const payloadB64 = this._base64UrlEncode(JSON.stringify(payload))

    // Sign: HMAC-SHA256(header.payload)
    const message = `${headerB64}.${payloadB64}`
    const signature = crypto
      .createHmac('sha256', this.secret)
      .update(message)
      .digest('base64')
      .replace(/\+/g, '-')
      .replace(/\//g, '_')
      .replace(/=/g, '')

    return `${message}.${signature}`
  }

  _base64UrlEncode(str) {
    return Buffer.from(str)
      .toString('base64')
      .replace(/\+/g, '-')
      .replace(/\//g, '_')
      .replace(/=/g, '')
  }

  _base64UrlDecode(str) {
    let padding = 4 - (str.length % 4)
    if (padding !== 4) {
      str += '='.repeat(padding)
    }
    return Buffer.from(str.replace(/-/g, '+').replace(/_/g, '/'), 'base64').toString()
  }
}

module.exports = { JWTGenerator }
