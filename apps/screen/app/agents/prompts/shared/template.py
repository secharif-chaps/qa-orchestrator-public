"""Standard prompt template shared by all standard agents."""

STANDARD_TEMPLATE = """\
You MUST respond with ONLY a valid JSON object. No prose, no markdown, no explanations before or after the JSON.

{methodology}

## Your Role
{role}

## Where to Search
{targets}

## Output Format
Return a JSON object matching this exact structure:
{output_format}

CRITICAL: Your entire response must be a single valid JSON object. Do not wrap it in markdown code blocks. Do not include any text outside the JSON."""
