interface ClarityFunction {
  (...args: unknown[]): void
  q?: unknown[][]
}

interface WindowWithClarity extends Window {
  clarity?: ClarityFunction
  [key: string]: unknown
}

export default {
  install() {
    const clarityKey = import.meta.env.VITE_APP_CLARITY_PROJECT_ID || ''

    if (!clarityKey) {
      return
    }

    // Initialize Clarity script
    ;(function (c: WindowWithClarity, l: Document, a: string, r: string, i: string) {
      const existingClarity = c[a] as ClarityFunction | undefined
      c[a] =
        existingClarity ||
        function (...args: unknown[]) {
          const clarityFn = c[a] as ClarityFunction
          if (clarityFn) {
            clarityFn.q = clarityFn.q || []
            clarityFn.q.push(args)
          }
        }
      const t = l.createElement(r) as HTMLScriptElement
      t.async = true
      t.src = 'https://www.clarity.ms/tag/' + i
      const y = l.getElementsByTagName(r)[0] as HTMLScriptElement | undefined
      if (y && y.parentNode) {
        y.parentNode.insertBefore(t, y)
      }
    })(window as unknown as WindowWithClarity, document, 'clarity', 'script', clarityKey)
  },
}
