import { Mistral } from '@mistralai/mistralai';

export const useAgent = () => {
  const runtimeConfig = useRuntimeConfig();
  const companyStore = useCompanyStore();

  const pending = ref(false);
  const companyName = ref<string | null>(null);
  const useStreaming = ref(true); // Toggle pour le mode streaming

  // Agent IDs
  const agentSourcedId = 'ag:cc2224b6:20250306:mint-sourced:f9c9d8b8';
  const agentChatId = 'ag:cc2224b6:20250224:mint:c6b81070'
  // Create Mistral client
  const client = new Mistral({ apiKey: runtimeConfig.public.mistralApiKey });

  /**
   * Extract partial data from an incomplete JSON string
   */
  const extractPartialData = (accumulated: string, currentCompanyName: string | null) => {
    // First, try to extract company name if we don't have one yet
    if (!currentCompanyName) {
      // Try multiple patterns for finding the company name
      const namePatterns = [
        /"profile"\s*:\s*\{\s*"name"\s*:\s*\{\s*"value"\s*:\s*"([^"]+)"/,
        /"insights"\s*:.*?"profile"\s*:\s*\{\s*"name"\s*:\s*\{\s*"value"\s*:\s*"([^"]+)"/s,
        /"name"\s*:\s*\{\s*"value"\s*:\s*"([^"]+)"/
      ];
      
      for (const pattern of namePatterns) {
        const nameMatch = accumulated.match(pattern);
        if (nameMatch) {
          const extractedName = nameMatch[1];
          companyName.value = extractedName;
          companyStore.initCompany(extractedName);
          return { companyName: extractedName, isValid: false };
        }
      }
    }

    if (currentCompanyName) {
      // Extract properties one by one for maximum granularity
      
      // 1. First look for profile properties (most important to show early)
      const profilePropertyPattern = /"profile"(?:[^{]*)\{(?:[^{]*)?"([^"]+)"\s*:\s*\{\s*"value"\s*:\s*"([^"]+)"(?:[^}]*)?"source"\s*:\s*"([^"]+)"/g;
      let match;
      while ((match = profilePropertyPattern.exec(accumulated)) !== null) {
        const propertyName = match[1];
        const propertyValue = match[2];
        const propertySource = match[3];
        
        // Update each property individually for maximum granularity
        if (propertyName && propertyValue) {
          const updatePath = `profile.${propertyName}`;
          const updateValue = { value: propertyValue, source: propertySource || "unknown" };
          companyStore.updateCompanyProperty(currentCompanyName, updatePath, updateValue);
        }
      }
      
      // 2. Look for company name again if not found yet (special case)
      if (!companyName.value) {
        const nameValuePattern = /"name"\s*:\s*\{\s*"value"\s*:\s*"([^"]+)"(?:[^}]*)?"source"\s*:\s*"([^"]+)"/g;
        let nameMatch;
        while ((nameMatch = nameValuePattern.exec(accumulated)) !== null) {
          const nameValue = nameMatch[1];
          const nameSource = nameMatch[2] || "unknown";
          
          if (nameValue) {
            companyName.value = nameValue;
            companyStore.updateCompanyProperty(
              currentCompanyName, 
              "profile.name", 
              { value: nameValue, source: nameSource }
            );
          }
        }
      }
      
      // 3. Extract individual properties from other sections
      const sections = [
        'products_and_services',
        'target_audience_and_customer_base',
        'digital_strategy_and_social_media',
        'csr',
        'recent_news',
        'social_media'
      ];
      
      // Process each section's properties
      sections.forEach(section => {
        // Look for properties inside this section
        const sectionPattern = new RegExp(`"${section}"\\s*:\\s*\\{([^}]+)\\}`, 'g');
        const sectionMatch = sectionPattern.exec(accumulated);
        
        if (sectionMatch) {
          const sectionContent = sectionMatch[1];
          
          // Try to find individual properties
          const propertyPattern = /"([^"]+)"\s*:\s*(?:\{\s*"value"\s*:\s*"([^"]+)"(?:[^}]*)?"source"\s*:\s*"([^"]+)"|(\[[^\]]*\]))/g;
          let propMatch;
          
          while ((propMatch = propertyPattern.exec(sectionContent)) !== null) {
            const propName = propMatch[1];
            const propValue = propMatch[2];
            const propSource = propMatch[3] || "unknown";
            const arrayValue = propMatch[4];
            
            if (propName) {
              if (propValue) {
                // It's a simple property with value/source
                companyStore.updateCompanyProperty(
                  currentCompanyName,
                  `${section}.${propName}`,
                  { value: propValue, source: propSource }
                );
              } else if (arrayValue) {
                // It's an array property
                try {
                  const parsedArray = JSON.parse(arrayValue);
                  companyStore.updateCompanyProperty(
                    currentCompanyName,
                    `${section}.${propName}`,
                    parsedArray
                  );
                } catch (e) {
                  // Ignore parsing errors
                }
              }
            }
          }
        }
      });
      
      // 4. Handle array items (like recent_news, social_media)
      const arrayValuePattern = /"(recent_news|product_range|partner_brands|private_labels|online_services|responsibility_initiatives|charity_actions)"\s*:\s*\[([^\]]+)\]/g;
      let arrayMatch;
      
      while ((arrayMatch = arrayValuePattern.exec(accumulated)) !== null) {
        const arrayName = arrayMatch[1];
        const arrayContent = arrayMatch[2];
        
        // Look for SourcedValue objects in the array
        const itemPattern = /\{\s*"value"\s*:\s*"([^"]+)"(?:[^}]*)?"source"\s*:\s*"([^"]+)"\s*\}/g;
        let items = [];
        let itemMatch;
        
        while ((itemMatch = itemPattern.exec(arrayContent)) !== null) {
          const itemValue = itemMatch[1];
          const itemSource = itemMatch[2] || "unknown";
          
          if (itemValue) {
            items.push({ value: itemValue, source: itemSource });
          }
        }
        
        if (items.length > 0) {
          // Determine the correct path based on array name
          let updatePath;
          if (arrayName === 'recent_news') {
            updatePath = 'recent_news';
          } else if (['product_range', 'partner_brands', 'private_labels'].includes(arrayName)) {
            updatePath = `products_and_services.${arrayName}`;
          } else if (arrayName === 'online_services') {
            updatePath = `digital_strategy_and_social_media.${arrayName}`;
          } else if (['responsibility_initiatives', 'charity_actions'].includes(arrayName)) {
            updatePath = `csr.${arrayName}`;
          }
          
          if (updatePath) {
            companyStore.updateCompanyProperty(currentCompanyName, updatePath, items);
          }
        }
      }
      
      // 5. Special handling for social_media array
      const socialMediaPattern = /"social_media"\s*:\s*\[([^\]]+)\]/g;
      const socialMatch = socialMediaPattern.exec(accumulated);
      
      if (socialMatch) {
        const socialContent = socialMatch[1];
        const socialItemPattern = /\{\s*"name"\s*:\s*"([^"]+)"(?:[^}]*)"url"\s*:\s*\{\s*"value"\s*:\s*"([^"]+)"(?:[^}]*)?"source"\s*:\s*"([^"]+)"\s*\}\s*\}/g;
        
        let socialItems = [];
        let socialItemMatch;
        
        while ((socialItemMatch = socialItemPattern.exec(socialContent)) !== null) {
          const platformName = socialItemMatch[1];
          const urlValue = socialItemMatch[2];
          const urlSource = socialItemMatch[3] || "unknown";
          
          if (platformName && urlValue) {
            socialItems.push({
              name: platformName,
              url: { value: urlValue, source: urlSource }
            });
          }
        }
        
        if (socialItems.length > 0) {
          companyStore.updateCompanyProperty(currentCompanyName, "social_media", socialItems);
        }
      }
    }

    return { companyName: currentCompanyName, isValid: false };
  };

  /**
   * Process a JSON chunk by trying to parse the accumulated JSON
   * If we get valid JSON properties, we update the company store
   */
  const processJsonChunk = (accumulated: string, currentCompanyName: string | null) => {
    // Try to parse the entire JSON first
    try {
      const parsedData = JSON.parse(accumulated);
      
      // Handle both structure possibilities
      // 1. Profile at top level (expected structure)
      // 2. Profile nested in insights (observed structure)
      const profile = parsedData.profile || parsedData.insights?.profile;
      const dataCompanyName = profile?.name?.value || currentCompanyName;
      
      // Fix structure if needed
      if (parsedData.insights?.profile) {
        // Extract nested properties to top level
        const nestedKeys = ['profile', 'products_and_services', 'target_audience_and_customer_base', 
                          'digital_strategy_and_social_media', 'csr', 'recent_news', 'social_media'];
        
        nestedKeys.forEach(key => {
          if (parsedData.insights[key]) {
            parsedData[key] = parsedData.insights[key];
            delete parsedData.insights[key];
          }
        });
      }
      
      if (dataCompanyName) {
        // Initialize company if we haven't already
        if (!companyName.value) {
          companyName.value = dataCompanyName;
          companyStore.initCompany(dataCompanyName);
        }
        
        // Update company data in store
        companyStore.updateCompanyProperties(dataCompanyName, parsedData);
        return { companyName: dataCompanyName, isValid: true };
      }
      
      return { companyName: currentCompanyName, isValid: true };
    } catch (e) {
      // If it's not valid JSON yet, try to extract what we can
      return extractPartialData(accumulated, currentCompanyName);
    }
  };

  /**
   * Effectue un appel non-streaming à l'agent
   */
  const generateClassic = async (company: string, website: string, onCompanyName?: (name: string) => void) => {
    const prompt = `Extrayez les informations de base sur l'entreprise ${company} à partir de son site web ${website}.`;
    
    // Reset company name
    companyName.value = null;
    pending.value = true;
    
    try {
      // Appel classique sans streaming
      const response = await client.agents.complete({
        agentId: agentSourcedId,
        messages: [{ role: 'user', content: prompt }],
        responseFormat: { type: 'json_object' }
      });

      // Extraction du contenu JSON
      if (response.choices && response.choices.length > 0) {
        const content = response.choices[0].message.content?.toString() || '';
        
        try {
          const result = JSON.parse(content);
          
          // Fix structure if needed
          if (result.insights?.profile) {
            // Extract nested properties to top level
            const nestedKeys = ['profile', 'products_and_services', 'target_audience_and_customer_base', 
                              'digital_strategy_and_social_media', 'csr', 'recent_news', 'social_media'];
            
            nestedKeys.forEach(key => {
              if (result.insights[key]) {
                result[key] = result.insights[key];
                delete result.insights[key];
              }
            });
          }
          
          // Get company name from either structure
          const profile = result.profile || result.insights?.profile;
          const extractedName = profile?.name?.value || company;
          
          if (extractedName) {
            companyName.value = extractedName;
            companyStore.initCompany(extractedName);
            companyStore.setCompanyData(extractedName, result);
            
            // Call the callback if provided
            if (onCompanyName) {
              onCompanyName(extractedName);
            }
          }
          
          pending.value = false;
          return result;
        } catch (e) {
          console.error('Failed to parse JSON response:', e);
          pending.value = false;
          return {};
        }
      }
      
      pending.value = false;
      return {};
    } catch (error) {
      console.error('Error during company data request:', error);
      pending.value = false;
      return {};
    }
  };

  /**
   * Effectue un appel avec streaming à l'agent
   */
  const generateStreaming = async (company: string, website: string, onCompanyName?: (name: string) => void) => {
    const prompt = `Extrayez les informations de base sur l'entreprise ${company} à partir de son site web ${website}.`;
    
    // Reset company name
    companyName.value = null;
    pending.value = true;
    
    try {
      // Use the sourced agent for streaming
      const stream = await client.agents.stream({
        agentId: agentSourcedId,
        messages: [{ role: 'user', content: prompt }],
        responseFormat: { type: 'json_object' },
        stream: true
      });

      let accumulated = '';
      let currentCompanyName = null;
      let lastProcessedLength = 0;
      
      for await (const chunk of stream) {
        if (chunk.data.choices && chunk.data.choices.length > 0) {
          const content = chunk.data.choices[0].delta?.content || '';
          accumulated += content;
          
          // Process more frequently - now with almost every chunk
          // The key is to process on every potential JSON element boundary
          // which could be a quote, bracket, brace or colon
          if (content.match(/["{},:\[\]]/)) {
            // Process even the smallest JSON fragment
            const { companyName: extractedName, isValid } = processJsonChunk(accumulated, currentCompanyName);
            
            // Update current company name if we found one
            if (extractedName && !currentCompanyName) {
              currentCompanyName = extractedName;
              
              // Call the callback if provided
              if (onCompanyName) {
                onCompanyName(extractedName);
              }
            }
            
            lastProcessedLength = accumulated.length;
          }
        }
      }
      
      // Final parse of the complete response
      try {
        const result = JSON.parse(accumulated);
        
        // Fix structure if needed
        if (result.insights?.profile) {
          // Extract nested properties to top level
          const nestedKeys = ['profile', 'products_and_services', 'target_audience_and_customer_base', 
                            'digital_strategy_and_social_media', 'csr', 'recent_news', 'social_media'];
          
          nestedKeys.forEach(key => {
            if (result.insights[key]) {
              result[key] = result.insights[key];
              delete result.insights[key];
            }
          });
        }
        
        // Get final company name from either structure
        const profile = result.profile || result.insights?.profile;
        const finalCompanyName = profile?.name?.value || currentCompanyName || company;
        
        if (finalCompanyName) {
          // Set the complete data
          companyStore.setCompanyData(finalCompanyName, result);
          
          // Ensure callback is called if not already
          if (!companyName.value && onCompanyName) {
            companyName.value = finalCompanyName;
            onCompanyName(finalCompanyName);
          }
        }
        
        pending.value = false;
        return result;
      } catch (e) {
        console.error('Failed to parse final JSON response:', e);
        pending.value = false;
        return {};
      }
    } catch (error) {
      console.error('Error during company data streaming:', error);
      pending.value = false;
      return {};
    }
  };

  /**
   * Fonction principale qui sélectionne le mode selon le paramètre useStreaming
   */
  const generate = async (company: string, website: string, onCompanyName?: (name: string) => void) => {
    return useStreaming.value 
      ? generateStreaming(company, website, onCompanyName)
      : generateClassic(company, website, onCompanyName);
  };

  const ask = async (question: string, companyName: string, context: any, onChunk: (text: string) => void) => {
    const company = companyStore.companies[companyName]
    
    try {
      const response = await client.agents.stream({
        agentId: agentChatId,
        messages: [
          {
            role: 'system',
            content: `Voici les informations de l'entreprise : ${JSON.stringify(company)}`
          },
          {
            role: 'system',
            content: `Voici le context des précedants messages : ${JSON.stringify(context)}`
          },
          {
            role: 'user',
            content: question
          }
        ],
        stream: true // Enable streaming
      })
      
      let fullResponse = ''
      
      // Handle the stream

      if(response){
        for await (const chunk of response) {

          if (chunk.data.choices && chunk.data.choices[0]?.delta?.content) {
            const contentChunk = chunk.data.choices[0].delta.content as string
            fullResponse += contentChunk
            onChunk(contentChunk) // Send each chunk to the callback
          }
        }
      }
      
      return fullResponse
    } catch (error) {
      console.error('Error with agent response:', error)
      return null
    }
  }

  /**
   * Bascule entre les modes streaming et classique
   */
  const toggleStreamingMode = (streaming: boolean) => {
    useStreaming.value = streaming;
  };

  return {
    ask,
    generate,
    pending,
    companyName,
    useStreaming,
    toggleStreamingMode
  };
};