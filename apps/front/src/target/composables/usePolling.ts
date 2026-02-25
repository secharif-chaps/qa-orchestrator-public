const abortControllers: Record<string, AbortController> = {}

const abort = (name: string) => {
  const controller = abortControllers[name]
  if (controller) {
    controller.abort()

    delete abortControllers[name]
  }
}

const createNewAbortController = (name: string): AbortController => {
  const controller = new AbortController()
  abortControllers[name] = controller
  return controller
}

const getAbortController = (name: string, forceReset: boolean = false): AbortController => {
  if (!abortControllers[name]) {
    return createNewAbortController(name)
  }

  if (forceReset) {
    abort(name)
    return createNewAbortController(name)
  }

  return abortControllers[name]
}

const start = (
  name: string,
  pollFunction: () => Promise<void>,
  onFinishIteration: () => void,
  onAbort: () => void,
  initialDelay: number = 1000,
  maxIterations: number = 50,
) => {
  const controller = getAbortController(name, true)

  const poll = async (iteration: number = 0, delay: number = initialDelay) => {
    if (controller.signal.aborted) {
      onAbort()
      return
    }

    try {
      await pollFunction()
    } catch (err) {
      console.error(`[Polling:${name}] Iteration ${iteration} failed`, err)
      throw err
    }

    if (controller.signal.aborted) {
      onAbort()
      return
    }

    if (iteration >= maxIterations - 1) {
      onFinishIteration()
      return
    }

    const nextDelay = delay + 1000 * Math.pow((iteration + 1) / maxIterations, 3)

    setTimeout(() => poll(iteration + 1, nextDelay), nextDelay)
  }

  poll()
}

export const usePolling = () => ({
  start,
  abort,
})
