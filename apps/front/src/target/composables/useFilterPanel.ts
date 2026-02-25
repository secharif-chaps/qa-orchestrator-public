import { onMounted, onUnmounted, type Ref } from 'vue'

export function useFilterPanel(elementRef: Ref<HTMLDivElement | null>, cssVar: string) {
  let resizeObserver: ResizeObserver | null = null

  const setGlobalWidth = (width: number) => {
    document.documentElement.style.setProperty(cssVar, `${width}px`)
  }

  const observeElement = (elementRef: Ref<HTMLElement | null>) => {
    if (!elementRef.value) return

    if (resizeObserver) {
      resizeObserver.disconnect()
    }

    resizeObserver = new ResizeObserver((entries) => {
      for (const entry of entries) {
        const width = entry.borderBoxSize[0].inlineSize
        setGlobalWidth(width)
      }
    })

    resizeObserver.observe(elementRef.value)

    const initialWidth = elementRef.value.offsetWidth
    setGlobalWidth(initialWidth)
  }

  const stopObserving = () => {
    if (resizeObserver) {
      resizeObserver.disconnect()
      resizeObserver = null
    }
  }

  onMounted(() => {
    observeElement(elementRef)
  })

  onUnmounted(() => {
    stopObserving()
  })

  return {}
}
