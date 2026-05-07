import { onMounted, onUnmounted, type Ref } from 'vue'

export const useFilterPanel = (elementRef: Ref<HTMLDivElement | null>, cssVar: string) => {
  let resizeObserver: ResizeObserver | null = null

  const setGlobalWidth = (width: number) => {
    document.documentElement.style.setProperty(cssVar, `${width}px`)
  }

  const observeElement = (target: Ref<HTMLElement | null>) => {
    if (!target.value) return

    if (resizeObserver) {
      resizeObserver.disconnect()
    }

    resizeObserver = new ResizeObserver((entries) => {
      for (const entry of entries) {
        const width = entry.borderBoxSize[0].inlineSize
        setGlobalWidth(width)
      }
    })

    resizeObserver.observe(target.value)

    const initialWidth = target.value.offsetWidth
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
}
