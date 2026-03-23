import { ref, computed } from 'vue'

const rateLimitedUntil = ref(0)
let cooldownTimer: ReturnType<typeof setTimeout> | null = null

export function useRateLimit() {
  const isRateLimited = computed(() => Date.now() < rateLimitedUntil.value)

  function getRemainingSeconds(): number {
    const remaining = rateLimitedUntil.value - Date.now()
    return remaining > 0 ? Math.ceil(remaining / 1000) : 0
  }

  function setRateLimit(seconds: number) {
    rateLimitedUntil.value = Date.now() + seconds * 1000

    if (cooldownTimer) {
      clearTimeout(cooldownTimer)
    }
    cooldownTimer = setTimeout(() => {
      rateLimitedUntil.value = 0
      cooldownTimer = null
    }, seconds * 1000)
  }

  return {
    isRateLimited,
    getRemainingSeconds,
    setRateLimit,
  }
}
