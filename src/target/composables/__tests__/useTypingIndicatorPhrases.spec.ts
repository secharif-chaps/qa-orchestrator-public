import { afterEach, beforeEach, describe, expect, it, vi, type MockInstance } from 'vitest'
import { nextTick, ref } from 'vue'

// Mock vue-i18n
vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string) => key,
  }),
}))

// Mock onUnmounted since we're not in a component context
const unmountCallbacks: (() => void)[] = []
vi.mock('vue', async () => {
  const actual = await vi.importActual('vue')
  return {
    ...actual,
    onUnmounted: (cb: () => void) => {
      unmountCallbacks.push(cb)
    },
  }
})

describe('useTypingIndicatorPhrases', () => {
  let mathRandomSpy: MockInstance

  beforeEach(() => {
    vi.useFakeTimers()
    unmountCallbacks.length = 0
    // Reset module cache to get fresh state for each test
    vi.resetModules()
  })

  afterEach(() => {
    vi.useRealTimers()
    if (mathRandomSpy) {
      mathRandomSpy.mockRestore()
    }
  })

  const importComposable = async () => {
    const module = await import('@target/composables/useTypingIndicatorPhrases')
    return module.useTypingIndicatorPhrases
  }

  it('selects a random typing indicator phrase on initialization', async () => {
    mathRandomSpy = vi.spyOn(Math, 'random').mockReturnValue(0.25)

    const useTypingIndicatorPhrases = await importComposable()
    const showReassurance = ref(false)
    const { typingIndicatorPhrase } = useTypingIndicatorPhrases(() => showReassurance.value)

    // 0.25 * 20 = 5, so index should be 5
    expect(typingIndicatorPhrase.value).toBe('watch_files.chat.typing_indicator.5')
  })

  it('rotates to a different phrase after 60 seconds', async () => {
    // First call returns 0.1 (index 2), second call for rotation returns 0.4 (index 8)
    mathRandomSpy = vi
      .spyOn(Math, 'random')
      .mockReturnValueOnce(0.1) // Initial typing indicator index: 2
      .mockReturnValueOnce(0.2) // Initial reassurance index: 4
      .mockReturnValueOnce(0.4) // Rotation: 8

    const useTypingIndicatorPhrases = await importComposable()
    const showReassurance = ref(false)
    const { typingIndicatorPhrase } = useTypingIndicatorPhrases(() => showReassurance.value)

    expect(typingIndicatorPhrase.value).toBe('watch_files.chat.typing_indicator.2')

    // Advance time by 60 seconds
    vi.advanceTimersByTime(60_000)

    expect(typingIndicatorPhrase.value).toBe('watch_files.chat.typing_indicator.8')
  })

  it('shows reassurance phrase when showReassurance is true', async () => {
    mathRandomSpy = vi
      .spyOn(Math, 'random')
      .mockReturnValueOnce(0.1) // Initial typing indicator index: 2
      .mockReturnValueOnce(0.3) // Initial reassurance index: 6
      .mockReturnValueOnce(0.5) // New reassurance index when switching: 10

    const useTypingIndicatorPhrases = await importComposable()
    const showReassurance = ref(false)
    const { reassurancePhrase } = useTypingIndicatorPhrases(() => showReassurance.value)

    // Initially no reassurance phrase
    expect(reassurancePhrase.value).toBeUndefined()

    // Enable reassurance
    showReassurance.value = true
    await nextTick()

    // Should now show reassurance phrase (new random index 10 when switching)
    expect(reassurancePhrase.value).toBe('watch_files.chat.reassurance_message.10')
  })

  it('rotates reassurance phrases when in reassurance mode', async () => {
    mathRandomSpy = vi
      .spyOn(Math, 'random')
      .mockReturnValueOnce(0.1) // Initial typing indicator index: 2
      .mockReturnValueOnce(0.2) // Initial reassurance index: 4
      .mockReturnValueOnce(0.5) // New reassurance index when switching: 10
      .mockReturnValueOnce(0.7) // Rotation reassurance index: 14

    const useTypingIndicatorPhrases = await importComposable()
    const showReassurance = ref(true)
    const { reassurancePhrase, typingIndicatorPhrase } = useTypingIndicatorPhrases(
      () => showReassurance.value,
    )

    // Initial reassurance phrase (new random when switching to reassurance mode)
    expect(reassurancePhrase.value).toBe('watch_files.chat.reassurance_message.10')

    // Typing indicator should still show its phrase
    expect(typingIndicatorPhrase.value).toBe('watch_files.chat.typing_indicator.2')

    // Advance time by 60 seconds - should rotate reassurance, not typing indicator
    vi.advanceTimersByTime(60_000)

    expect(reassurancePhrase.value).toBe('watch_files.chat.reassurance_message.14')
    // Typing indicator unchanged
    expect(typingIndicatorPhrase.value).toBe('watch_files.chat.typing_indicator.2')
  })

  it('avoids selecting the same phrase index when rotating', async () => {
    // Mock to return same index first, then different
    mathRandomSpy = vi
      .spyOn(Math, 'random')
      .mockReturnValueOnce(0.25) // Initial index: 5
      .mockReturnValueOnce(0.3) // Initial reassurance index: 6
      .mockReturnValueOnce(0.25) // First rotation attempt: 5 (same, should retry)
      .mockReturnValueOnce(0.25) // Second rotation attempt: 5 (same, should retry)
      .mockReturnValueOnce(0.5) // Third rotation attempt: 10 (different, accepted)

    const useTypingIndicatorPhrases = await importComposable()
    const showReassurance = ref(false)
    const { typingIndicatorPhrase } = useTypingIndicatorPhrases(() => showReassurance.value)

    expect(typingIndicatorPhrase.value).toBe('watch_files.chat.typing_indicator.5')

    // Advance time by 60 seconds
    vi.advanceTimersByTime(60_000)

    // Should have retried until getting a different index
    expect(typingIndicatorPhrase.value).toBe('watch_files.chat.typing_indicator.10')
  })

  it('cleans up interval on unmount', async () => {
    const clearIntervalSpy = vi.spyOn(global, 'clearInterval')

    const useTypingIndicatorPhrases = await importComposable()
    const showReassurance = ref(false)
    useTypingIndicatorPhrases(() => showReassurance.value)

    // Simulate unmount
    unmountCallbacks.forEach((cb) => cb())

    expect(clearIntervalSpy).toHaveBeenCalled()
    clearIntervalSpy.mockRestore()
  })

  it('keeps typing indicator phrase stable when transitioning to reassurance mode', async () => {
    mathRandomSpy = vi
      .spyOn(Math, 'random')
      .mockReturnValueOnce(0.15) // Initial typing indicator index: 3
      .mockReturnValueOnce(0.35) // Initial reassurance index: 7
      .mockReturnValueOnce(0.6) // New reassurance index when switching: 12

    const useTypingIndicatorPhrases = await importComposable()
    const showReassurance = ref(false)
    const { typingIndicatorPhrase, reassurancePhrase } = useTypingIndicatorPhrases(
      () => showReassurance.value,
    )

    const initialPhrase = typingIndicatorPhrase.value
    expect(initialPhrase).toBe('watch_files.chat.typing_indicator.3')

    // Switch to reassurance mode
    showReassurance.value = true
    await nextTick()

    // Typing indicator phrase should remain the same
    expect(typingIndicatorPhrase.value).toBe(initialPhrase)
    // Reassurance phrase should now be shown
    expect(reassurancePhrase.value).toBe('watch_files.chat.reassurance_message.12')
  })

  it('multiple rotations work correctly', async () => {
    mathRandomSpy = vi
      .spyOn(Math, 'random')
      .mockReturnValueOnce(0.0) // Initial index: 0
      .mockReturnValueOnce(0.1) // Initial reassurance: 2
      .mockReturnValueOnce(0.25) // 1st rotation: 5
      .mockReturnValueOnce(0.5) // 2nd rotation: 10
      .mockReturnValueOnce(0.75) // 3rd rotation: 15

    const useTypingIndicatorPhrases = await importComposable()
    const showReassurance = ref(false)
    const { typingIndicatorPhrase } = useTypingIndicatorPhrases(() => showReassurance.value)

    expect(typingIndicatorPhrase.value).toBe('watch_files.chat.typing_indicator.0')

    vi.advanceTimersByTime(60_000)
    expect(typingIndicatorPhrase.value).toBe('watch_files.chat.typing_indicator.5')

    vi.advanceTimersByTime(60_000)
    expect(typingIndicatorPhrase.value).toBe('watch_files.chat.typing_indicator.10')

    vi.advanceTimersByTime(60_000)
    expect(typingIndicatorPhrase.value).toBe('watch_files.chat.typing_indicator.15')
  })
})
