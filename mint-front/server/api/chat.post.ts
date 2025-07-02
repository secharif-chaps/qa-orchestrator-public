export default defineEventHandler(async (event) => {
  try {
    const body = await readBody(event)
    
    const n8nWebhookUrl = `http://ec2-34-244-245-92.eu-west-1.compute.amazonaws.com:5678/webhook/96b9765e-6c96-4493-bf56-a65905f7a6bc/chat`
    
    // Forward the request to n8n webhook using native fetch
    const response = await fetch(n8nWebhookUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(body)
    })
    
    if (!response.ok) {
      console.error('N8N webhook responded with error:', response.status, response.statusText)
      throw createError({
        statusCode: response.status,
        statusMessage: `N8N webhook error: ${response.statusText}`
      })
    }
    
    const data = await response.json()
    console.log('N8N response:', data)
    
    return data
  } catch (error) {
    console.error('Error in chat API:', error)
    throw createError({
      statusCode: 500,
      statusMessage: error.message || 'Failed to process chat request'
    })
  }
})