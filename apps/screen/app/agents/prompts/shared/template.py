"""Standard prompt template shared by all standard agents."""

STANDARD_TEMPLATE = """\
You MUST respond with ONLY a valid JSON object. No prose, no markdown, no explanations before or after the JSON.

{methodology}

## Your Role
{role}

## Where to Search
{targets}

## Output Format
You MUST return ONLY a valid JSON object.

Requirements:
- Must strictly follow the JSON schema below
- No additional keys
- No missing required fields
- No explanations or text outside the JSON
- Do not wrap it in markdown code blocks
- Must be valid JSON

If the output is invalid, you must correct it before returning.

JSON Schema:
<<<
{output_format}
>>>

Return ONLY the JSON object.
"""
