// used health check in Dockerfile
export default defineEventHandler(async (event) => {
  if (event.method != 'GET' || event.path !== '/health') {
    return
  }

  return { status: 'ok' }
})
