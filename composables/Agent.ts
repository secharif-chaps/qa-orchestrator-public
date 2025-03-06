import { Mistral } from '@mistralai/mistralai';


const agentId = 'ag:cc2224b6:20250224:mint:c6b81070'
const agentSourcedId = 'ag:cc2224b6:20250306:mint-sourced:f9c9d8b8'

const graphAgentId = 'ag:cc2224b6:20250227:mint-graph:8fde7734'


export const useAgent = () => {
  const runtimeConfig = useRuntimeConfig()
  const companyStore = useCompanyStore()

  const pending = ref(false)
  const companyName = ref<string | null>(null)

  const client = new Mistral({ apiKey: runtimeConfig.public.mistralApiKey })

  // Define regexes for all individual properties we want to extract
  const propertyRegexes = {
    // Profile properties
    'profile.name': /"name"\s*:\s*"([^"]+)"/i,
    'profile.website': /"website"\s*:\s*"([^"]+)"/i,
    'profile.group_name': /"group_name"\s*:\s*"([^"]+)"/i,
    'profile.business_line': /"business_line"\s*:\s*"([^"]+)"/i,
    'profile.catchphrase': /"catchphrase"\s*:\s*"([^"]+)"/i,
    'profile.establishment_year': /"establishment_year"\s*:\s*"?(\d+)"?/i,
    'profile.employee_count': /"employee_count"\s*:\s*"?([^",\}\s]+)"?/i,
    'profile.revenue': /"revenue"\s*:\s*"([^"]+)"/i,
    
    // Insights
    'insights': /"insights"\s*:\s*"([^"]+)"/i,
    
    // Individual product and services properties
    'products_and_services.product_range': /"product_range"\s*:\s*(\[(?:\s*"[^"]*"\s*,?)*\])/i,
    'products_and_services.partner_brands': /"partner_brands"\s*:\s*(\[(?:\s*"[^"]*"\s*,?)*\])/i,
    'products_and_services.private_labels': /"private_labels"\s*:\s*(\[(?:\s*"[^"]*"\s*,?)*\])/i,
    
    // Target audience properties
    'target_audience_and_customer_base.customer_type': /"customer_type"\s*:\s*"([^"]+)"/i,
    'target_audience_and_customer_base.marketing_positioning': /"marketing_positioning"\s*:\s*"([^"]+)"/i,
    
    // Digital strategy properties
    'digital_strategy_and_social_media.digital_strategy': /"digital_strategy"\s*:\s*"([^"]+)"/i,
    'digital_strategy_and_social_media.loyalty_program': /"loyalty_program"\s*:\s*"([^"]+)"/i,
    'digital_strategy_and_social_media.online_services': /"online_services"\s*:\s*(\[(?:\s*"[^"]*"\s*,?)*\])/i,
    
    // CSR properties
    'csr.responsibility_initiatives': /"responsibility_initiatives"\s*:\s*(\[(?:\s*"[^"]*"\s*,?)*\])/i,
    'csr.charity_actions': /"charity_actions"\s*:\s*(\[(?:\s*"[^"]*"\s*,?)*\])/i,
    
    // News
    'recent_news': /"recent_news"\s*:\s*(\[(?:\s*"[^"]*"\s*,?)*\])/i,
    
    // Social media (this is a bit complex to parse as a regex, might need special handling)
    'social_media': /"social_media"\s*:\s*(\{[^}]+\})/i
  }

  // Extract property value from accumulated text using regex
  const extractProperty = (accumulated, propertyPath, regex) => {
    const match = accumulated.match(regex)
    if (!match) return null
    
    let value = match[1]
    
    // If it looks like an array, parse it
    if (value.startsWith('[') && value.endsWith(']')) {
      try {
        return JSON.parse(value)
      } catch (e) {
        console.log(`Failed to parse array for ${propertyPath}`, e)
        return null
      }
    } 
    // If it looks like an object, parse it
    else if (value.startsWith('{') && value.endsWith('}')) {
      try {
        return JSON.parse(value)
      } catch (e) {
        console.log(`Failed to parse object for ${propertyPath}`, e)
        return null
      }
    }
    
    // Otherwise return the string value
    return value
  }

  // Update a specific property in the company object
  const updateCompanyProperty = (companyObj, path, value) => {
    if (!value) return companyObj
    
    const parts = path.split('.')
    const result = { ...companyObj }
    
    let current = result
    for (let i = 0; i < parts.length - 1; i++) {
      const part = parts[i]
      if (!current[part]) {
        current[part] = {}
      }
      current = current[part]
    }
    
    current[parts[parts.length - 1]] = value
    return result
  }

  // Scan accumulated text for all properties and update any found
  const scanForProperties = (accumulated, currentObj = {}, currentName = null) => {
    let updated = false
    let updatedObj = { ...currentObj }
    
    // Check each property regex
    for (const [propertyPath, regex] of Object.entries(propertyRegexes)) {
      const value = extractProperty(accumulated, propertyPath, regex)
      
      // If we found a value, update the object
      if (value !== null) {
        const newObj = updateCompanyProperty(updatedObj, propertyPath, value)
        
        // Special case for profile.name - update currentName if found
        if (propertyPath === 'profile.name' && !currentName) {
          currentName = value
        }
        
        // If anything changed, mark as updated
        if (JSON.stringify(newObj) !== JSON.stringify(updatedObj)) {
          updatedObj = newObj
          updated = true
        }
      }
    }
    
    // Special case for social_media which is harder to extract with regex
    try {
      const socialMediaMatch = accumulated.match(/"social_media"\s*:\s*(\{[^}]+\}|\[[^\]]+\])/s)
      if (socialMediaMatch) {
        const socialMedia = JSON.parse(socialMediaMatch[1])
        if (socialMedia) {
          updatedObj.social_media = socialMedia
          updated = true
        }
      }
    } catch (e) {
      // Ignore parsing errors for social media
    }
    
    return { 
      updated,
      obj: updatedObj,
      currentName
    }
  }

  // Generates company information
  const generate = async (company: string, website: string, onCompanyName?: (name: string) => void) => {
    const prompt = `Extrayez les informations de base sur l'entreprise ${company} à partir de son site web ${website}.`

    // Reset company name
    companyName.value = null

    pending.value = true
    
    try {
      // Use the correct Mistral API for streaming
      const stream = await client.agents.stream({
        agentId,
        messages: [{ role: 'user', content: prompt }],
        responseFormat: { type: 'json_object' },
        stream: true
      })

      let accumulated = ''
      let currentCompanyName = null
      let currentObj = {}
      
      for await (const chunk of stream) {
        if (chunk.data.choices && chunk.data.choices.length > 0) {
          const content = chunk.data.choices[0].delta?.content || ''
          accumulated += content
          
          // Scan for all properties we can extract
          const { updated, obj, currentName } = scanForProperties(accumulated, currentObj, currentCompanyName)
          
          // If we found any new properties, update the store
          if (updated) {
            currentObj = obj
            
            // Handle company name if it's new
            if (currentName && !currentCompanyName) {
              currentCompanyName = currentName
              companyName.value = currentName
              
              // Initialize company in store if needed
              companyStore.initCompany(currentName)
              
              // Trigger callback
              if (onCompanyName) {
                onCompanyName(currentName)
              }
            }
            
            // Update the store with our current object if we have a company name
            if (currentCompanyName) {
              companyStore.updateCompanyProperties(currentCompanyName, currentObj)
            }
          }
        }
      }
      
      // Final parsing of the complete response
      try {
        const result = JSON.parse(accumulated)
        pending.value = false
        
        // Get the final company name
        const finalCompanyName = result.profile?.name || currentCompanyName
        
        if (finalCompanyName) {
          // Set the final complete data
          companyStore.setCompanyData(finalCompanyName, result)
          
          // Ensure callback is called if it wasn't already
          if (!companyName.value && onCompanyName) {
            companyName.value = finalCompanyName
            onCompanyName(finalCompanyName)
          }
        }
        
        return result
      } catch (e) {
        console.error('Failed to parse final JSON response:', e)
        pending.value = false
        return {}
      }
    } catch (error) {
      console.error('Error during company data streaming:', error)
      pending.value = false
      return {}
    }
  }

  // Generates graph data
  const generateGraph = async (company: string) => {
    const prompt = `Génere moi le graph pour l'entreprise ${company}`

    pending.value = true
    
    try {
      // For graph data, use streaming but update store as we go
      const stream = await client.agents.stream({
        agentId: graphAgentId,
        messages: [{ role: 'user', content: prompt }],
        responseFormat: { type: 'json_object' },
        stream: true
      })

      let accumulated = ''
      let nodesFound = false
      let edgesFound = false
      
      for await (const chunk of stream) {
        if (chunk.data.choices && chunk.data.choices.length > 0) {
          const content = chunk.data.choices[0].delta?.content || ''
          accumulated += content
          
          if (!nodesFound) {
            // Try to extract nodes array
            const nodesMatch = accumulated.match(/"nodes"\s*:\s*(\[(?:\s*\{[^}]*\}\s*,?)*\])/s)
            if (nodesMatch) {
              try {
                const nodes = JSON.parse(nodesMatch[1])
                if (nodes && nodes.length > 0 && companyName.value) {
                  // Update just the nodes part of the graph
                  companyStore.updateGraphProperty(companyName.value, 'nodes', nodes)
                  nodesFound = true
                }
              } catch (e) {
                // Ignore parsing errors, nodes might be incomplete
              }
            }
          }
          
          if (!edgesFound) {
            // Try to extract edges array
            const edgesMatch = accumulated.match(/"edges"\s*:\s*(\[(?:\s*\{[^}]*\}\s*,?)*\])/s)
            if (edgesMatch) {
              try {
                const edges = JSON.parse(edgesMatch[1])
                if (edges && edges.length > 0 && companyName.value) {
                  // Update just the edges part of the graph
                  companyStore.updateGraphProperty(companyName.value, 'edges', edges)
                  edgesFound = true
                }
              } catch (e) {
                // Ignore parsing errors, edges might be incomplete
              }
            }
          }
        }
      }
      
      // Final parsing of the complete response
      try {
        const result = JSON.parse(accumulated)
        pending.value = false
        
        const graphData = {
          nodes: result.nodes || [],
          edges: result.edges || []
        }
        
        // Update store with final graph data
        if (companyName.value) {
          companyStore.setGraphData(companyName.value, graphData)
        }
        
        return graphData
      } catch (e) {
        console.error('Failed to parse final JSON graph response:', e)
        pending.value = false
        return { nodes: [], edges: [] }
      }
    } catch (error) {
      console.error('Error during graph data streaming:', error)
      pending.value = false
      return { nodes: [], edges: [] }
    }
  }

  return {
    generate,
    generateGraph,
    pending,
    companyName
  }
}