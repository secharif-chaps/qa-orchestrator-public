import httpx
import logging
from typing import Dict, Any, Optional, List
from app.core.config import settings

logger = logging.getLogger(__name__)

class N8nClient:
    """Client for interacting with n8n workflows"""
    
    def __init__(self, base_url: str = None, webhook_id: str = None):
        self.base_url = base_url or settings.N8N_BASE_URL
        self.webhook_id = webhook_id or settings.N8N_WEBHOOK_ID
        
    async def trigger_workflow(self, company: str, website: str, query: str) -> Dict[str, Any]:
        """
        Trigger an n8n workflow for a specific company data query
        
        Args:
            company: Company name
            website: Company website
            query: Type of query (profile, team, products, etc.)
            
        Returns:
            Response data from the n8n workflow
        """
        url = f"{self.base_url}/webhook/{self.webhook_id}"
        
        # Log the request details
        print(f"Request data: {{'company': {company}, 'website': {website}, 'query': {query}}}")
        
        try:
            async with httpx.AsyncClient() as client:
                # Increased timeout to 5 minutes
                response = await client.post(
                    url,
                    json={
                        "company": company,
                        "website": website,
                        "query": query
                    },
                    timeout=300.0  # 5 minutes timeout
                )
                
                # Log the raw response for debugging
                # print(f"N8n raw response status: {response.status_code}")
                # print(f"N8n raw response headers: {response.headers}")
                print(f"N8n raw response body: {response.text}")
                
                if response.status_code != 200:
                    error_msg = f"N8n workflow returned non-200 status code: {response.status_code}"
                    print(error_msg)
                    raise Exception(error_msg)
                
                try:
                    # Handle empty or whitespace-only responses
                    response_text = response.text.strip()
                    if not response_text:
                        print("N8n workflow returned empty response, using empty dict")
                        return {}
                    
                    # Parse the response text directly
                    import json
                    json_response = json.loads(response_text)
                    
                    if json_response is None:
                        print("N8n workflow returned null response, using empty dict")
                        return {}
                    
                    # If the response is a list, take the first item
                    if isinstance(json_response, list):
                        if not json_response:
                            print("N8n workflow returned empty list, using empty dict")
                            return {}
                        json_response = json_response[0]
                    
                    # Ensure we have a dictionary
                    if not isinstance(json_response, dict):
                        print(f"N8n workflow returned unexpected type: {type(json_response)}, converting to dict")
                        # Try to wrap non-dict responses in a dict
                        json_response = {"data": json_response}
                    

                    print(f"N8n workflow returned parsed response: {json_response}")
                    return json_response
                except json.JSONDecodeError as e:
                    # Handle non-JSON responses gracefully
                    print(f"Failed to parse n8n response as JSON: {str(e)}")
                    print(f"Response text: '{response.text}'")
                    print("Returning empty dict as fallback")
                    return {}
                except Exception as e:
                    error_msg = f"Error processing n8n response: {str(e)}"
                    print(error_msg)
                    print("Returning empty dict as fallback")
                    return {}
                
        except httpx.TimeoutException as e:
            error_msg = f"Request to n8n timed out after 5 minutes. This might indicate that the workflow is taking longer than expected to complete."
            print(error_msg)
            raise Exception(error_msg) from e
        except httpx.HTTPError as e:
            error_msg = f"HTTP error occurred: {str(e)}"
            if hasattr(e, 'response') and e.response is not None:
                error_msg += f"\nStatus code: {e.response.status_code}"
                error_msg += f"\nResponse body: {e.response.text}"
            print(error_msg)
            raise Exception(error_msg) from e
        except Exception as e:
            error_msg = f"Error triggering n8n workflow: {str(e)}"
            print(error_msg)
            raise Exception(error_msg) from e
            
    async def check_workflow_status(self, execution_id: str) -> Dict[str, Any]:
        """
        Check status of a workflow execution
        
        Args:
            execution_id: ID of the workflow execution
            
        Returns:
            Status data for the execution
        """
        url = f"{self.base_url}/executions/{execution_id}"
        
        async with httpx.AsyncClient() as client:
            response = await client.get(url)
            await response.raise_for_status()
            return await response.json()
    
    async def send_chat_message(self, message: str, company_context: Dict[str, Any], chat_history: List[Dict[str, str]]) -> Dict[str, Any]:
        """
        Send a chat message to the n8n chat workflow
        
        Args:
            message: User's chat message
            company_context: Company context data
            chat_history: Previous chat messages
            
        Returns:
            Response from the chat workflow
        """
        url = f"{self.base_url}/webhook/{settings.N8N_CHAT_WEBHOOK_ID}/chat"
        
        logger.info(f"Sending chat message to n8n workflow: {url}")
        
        try:
            async with httpx.AsyncClient(timeout=60.0) as client:
                response = await client.post(
                    url,
                    json={
                        "message": message,
                        "companyContext": company_context,
                        "chatHistory": chat_history
                    }
                )
                
                logger.info(f"Chat response status: {response.status_code}")
                
                if response.status_code != 200:
                    error_msg = f"N8n chat workflow returned non-200 status code: {response.status_code}"
                    logger.error(error_msg)
                    raise Exception(error_msg)
                
                try:
                    response_text = response.text.strip()
                    if not response_text:
                        logger.warning("N8n chat workflow returned empty response")
                        return {"response": "I'm sorry, I couldn't generate a response. Please try again.", "status": "error"}
                    
                    import json
                    import ast
                    json_response = json.loads(response_text)
                    
                    if json_response is None:
                        logger.warning("N8n chat workflow returned null response")
                        return {"response": "I'm sorry, I couldn't generate a response. Please try again.", "status": "error"}
                    
                    # Check if the response contains stringified JSON with 'output' field
                    if isinstance(json_response.get("response"), str):
                        try:
                            # Try to parse the stringified response
                            stringified_response = json_response["response"]
                            logger.info(f"Attempting to parse stringified response: {stringified_response[:100]}...")
                            
                            # Handle Python dict string format like "{'output': '...'}" or "{'output': \"...\"}"
                            if stringified_response.startswith("{") and stringified_response.endswith("}"):
                                # Try ast.literal_eval first for Python dict format
                                try:
                                    parsed_response = ast.literal_eval(stringified_response)
                                    if isinstance(parsed_response, dict) and "output" in parsed_response:
                                        json_response["response"] = parsed_response["output"]
                                        logger.info("Successfully parsed stringified response with ast.literal_eval")
                                    else:
                                        logger.warning("Parsed response doesn't contain 'output' field")
                                except (ValueError, SyntaxError):
                                    # If ast.literal_eval fails, try json.loads
                                    try:
                                        parsed_response = json.loads(stringified_response)
                                        if isinstance(parsed_response, dict) and "output" in parsed_response:
                                            json_response["response"] = parsed_response["output"]
                                            logger.info("Successfully parsed stringified response with json.loads")
                                        else:
                                            logger.warning("JSON parsed response doesn't contain 'output' field")
                                    except json.JSONDecodeError:
                                        logger.warning("Failed to parse with both ast.literal_eval and json.loads")
                        
                        except Exception as parse_error:
                            logger.warning(f"Could not parse stringified response: {parse_error}. Using original response.")
                    
                    logger.info("Chat response received successfully")
                    return json_response
                    
                except json.JSONDecodeError as e:
                    logger.error(f"Failed to parse n8n chat response as JSON: {str(e)}")
                    return {"response": "I'm sorry, there was an error processing your message. Please try again.", "status": "error"}
                except Exception as e:
                    logger.error(f"Error processing n8n chat response: {str(e)}")
                    return {"response": "I'm sorry, there was an error processing your message. Please try again.", "status": "error"}
                
        except httpx.TimeoutException as e:
            error_msg = f"Chat request to n8n timed out after 60 seconds"
            logger.error(error_msg)
            raise Exception(error_msg) from e
        except httpx.HTTPError as e:
            error_msg = f"HTTP error occurred in chat request: {str(e)}"
            logger.error(error_msg)
            raise Exception(error_msg) from e
        except Exception as e:
            error_msg = f"Error in chat workflow: {str(e)}"
            logger.error(error_msg)
            raise Exception(error_msg) from e 