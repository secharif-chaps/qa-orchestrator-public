/**
 * Shared format validators. Lightweight and synchronous — for richer schemas
 * (cross-field rules, async server checks…) prefer a dedicated composable.
 */

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

export const isValidEmail = (email: string): boolean => EMAIL_REGEX.test(email)
