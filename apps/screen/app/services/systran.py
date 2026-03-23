"""SYSTRAN Translation API client.

This module provides a client for the SYSTRAN Translation API,
supporting text translation with batch processing capabilities.

API Documentation: https://docs.systran.net/translateAPI/translation/
"""

from dataclasses import dataclass

import httpx

from app.core.config import settings
from app.core.logging_config import get_logger

logger = get_logger(__name__)


@dataclass
class TranslationResult:
    """Result of a translation request."""

    source_text: str
    translated_text: str
    source_language: str
    target_language: str
    detected_language: str | None = None


class SystranError(Exception):
    """Base exception for SYSTRAN API errors."""

    def __init__(self, message: str, status_code: int | None = None):
        super().__init__(message)
        self.status_code = status_code


class SystranClient:
    """Client for SYSTRAN Translation API.

    Supports text translation with automatic language detection
    and batch processing.

    Attributes:
        api_url: SYSTRAN API base URL
        api_key: API key for authentication
    """

    # Language code mapping (our codes -> SYSTRAN codes)
    # SYSTRAN uses ISO 639-1 codes which match ours
    LANGUAGE_MAP = {
        "en": "en",
        "fr": "fr",
        "es": "es",
        "de": "de",
        "pt": "pt",
    }

    def __init__(
        self,
        api_url: str | None = None,
        api_key: str | None = None,
    ):
        """Initialize SYSTRAN client.

        Args:
            api_url: SYSTRAN API URL (defaults to settings)
            api_key: API key (defaults to settings)
        """
        self.api_url = api_url or settings.SYSTRAN_API_URL
        self.api_key = api_key or settings.SYSTRAN_API_KEY

        if not self.api_key:
            raise SystranError("SYSTRAN_API_KEY is not configured")

    def _get_headers(self) -> dict[str, str]:
        """Get request headers with authentication."""
        return {
            "Authorization": f"Key {self.api_key}",
            "Content-Type": "application/json",
            "Accept": "application/json",
        }

    async def translate_text(
        self,
        text: str,
        target_language: str,
        source_language: str = "en",
    ) -> TranslationResult:
        """Translate a single text string.

        Args:
            text: Text to translate
            target_language: Target language code (e.g., 'es', 'de')
            source_language: Source language code (default: 'en')

        Returns:
            TranslationResult with translated text

        Raises:
            SystranError: If translation fails
        """
        if not text or not text.strip():
            return TranslationResult(
                source_text=text,
                translated_text=text,
                source_language=source_language,
                target_language=target_language,
            )

        # Map language codes
        source = self.LANGUAGE_MAP.get(source_language, source_language)
        target = self.LANGUAGE_MAP.get(target_language, target_language)

        url = f"{self.api_url}/translation/text/translate"

        payload = {
            "input": text,
            "source": source,
            "target": target,
            "withInfo": True,  # Get language detection info
        }

        logger.debug(
            f"Translating text: {source} -> {target}",
            extra={"text_length": len(text)},
        )

        try:
            async with httpx.AsyncClient(timeout=30.0) as client:
                response = await client.post(
                    url,
                    json=payload,
                    headers=self._get_headers(),
                )

                if response.status_code != 200:
                    error_detail = response.text
                    logger.error(
                        f"SYSTRAN API error: {response.status_code}",
                        extra={"detail": error_detail},
                    )
                    raise SystranError(
                        f"Translation failed: {error_detail}",
                        status_code=response.status_code,
                    )

                data = response.json()

                # Extract translation from response
                # Response format: {"outputs": [{"output": "translated text", ...}]}
                outputs = data.get("outputs", [])
                if not outputs:
                    raise SystranError("No translation output received")

                output = outputs[0]
                translated_text = output.get("output", "")

                # Get detected language if available
                detected_lang = None
                info = output.get("info", {})
                if info:
                    detected_lang = info.get("detectedLanguage")

                logger.debug(
                    f"Translation complete: {len(text)} -> {len(translated_text)} chars",
                    extra={
                        "source": source,
                        "target": target,
                        "detected": detected_lang,
                    },
                )

                return TranslationResult(
                    source_text=text,
                    translated_text=translated_text,
                    source_language=source_language,
                    target_language=target_language,
                    detected_language=detected_lang,
                )

        except httpx.TimeoutException as e:
            logger.error(f"SYSTRAN API timeout: {e}")
            raise SystranError("Translation request timed out") from e
        except httpx.RequestError as e:
            logger.error(f"SYSTRAN API request error: {e}")
            raise SystranError(f"Translation request failed: {e}") from e

    async def translate_batch(
        self,
        texts: list[str],
        target_language: str,
        source_language: str = "en",
    ) -> list[TranslationResult]:
        """Translate multiple texts in a single API call.

        SYSTRAN API supports batch translation via repeated 'input' parameters.

        Args:
            texts: List of texts to translate
            target_language: Target language code
            source_language: Source language code (default: 'en')

        Returns:
            List of TranslationResult objects

        Raises:
            SystranError: If translation fails
        """
        if not texts:
            return []

        # Filter out empty texts but track their positions
        non_empty_indices = []
        non_empty_texts = []
        for i, text in enumerate(texts):
            if text and text.strip():
                non_empty_indices.append(i)
                non_empty_texts.append(text)

        if not non_empty_texts:
            # All texts were empty, return empty results
            return [
                TranslationResult(
                    source_text=t,
                    translated_text=t,
                    source_language=source_language,
                    target_language=target_language,
                )
                for t in texts
            ]

        # Map language codes
        source = self.LANGUAGE_MAP.get(source_language, source_language)
        target = self.LANGUAGE_MAP.get(target_language, target_language)

        url = f"{self.api_url}/translation/text/translate"

        # SYSTRAN batch format: repeat 'input' key for each text
        # We use query params for batch since JSON body uses single input
        params = {
            "source": source,
            "target": target,
            "withInfo": "true",
        }

        # Add each text as a separate 'input' parameter
        # httpx handles repeated params correctly
        param_list = [(k, v) for k, v in params.items()]
        for text in non_empty_texts:
            param_list.append(("input", text))

        logger.info(
            f"Batch translating {len(non_empty_texts)} texts: {source} -> {target}",
        )

        try:
            async with httpx.AsyncClient(timeout=60.0) as client:
                response = await client.get(
                    url,
                    params=param_list,
                    headers=self._get_headers(),
                )

                if response.status_code != 200:
                    error_detail = response.text
                    logger.error(
                        f"SYSTRAN batch API error: {response.status_code}",
                        extra={"detail": error_detail},
                    )
                    raise SystranError(
                        f"Batch translation failed: {error_detail}",
                        status_code=response.status_code,
                    )

                data = response.json()
                outputs = data.get("outputs", [])

                if len(outputs) != len(non_empty_texts):
                    logger.warning(f"Output count mismatch: expected {len(non_empty_texts)}, got {len(outputs)}")

                # Build results, mapping back to original positions
                results: list[TranslationResult] = []
                output_idx = 0

                for i, original_text in enumerate(texts):
                    if i in non_empty_indices:
                        # This was a non-empty text that was translated
                        if output_idx < len(outputs):
                            output = outputs[output_idx]
                            translated_text = output.get("output", original_text)
                            detected_lang = output.get("info", {}).get("detectedLanguage")
                        else:
                            translated_text = original_text
                            detected_lang = None

                        results.append(
                            TranslationResult(
                                source_text=original_text,
                                translated_text=translated_text,
                                source_language=source_language,
                                target_language=target_language,
                                detected_language=detected_lang,
                            )
                        )
                        output_idx += 1
                    else:
                        # Empty text, return as-is
                        results.append(
                            TranslationResult(
                                source_text=original_text,
                                translated_text=original_text,
                                source_language=source_language,
                                target_language=target_language,
                            )
                        )

                logger.info(f"Batch translation complete: {len(results)} results")
                return results

        except httpx.TimeoutException as e:
            logger.error(f"SYSTRAN batch API timeout: {e}")
            raise SystranError("Batch translation request timed out") from e
        except httpx.RequestError as e:
            logger.error(f"SYSTRAN batch API request error: {e}")
            raise SystranError(f"Batch translation request failed: {e}") from e

    async def get_supported_languages(self) -> list[dict]:
        """Get list of supported language pairs from SYSTRAN.

        Returns:
            List of supported language configurations
        """
        url = f"{self.api_url}/translation/supportedLanguages"

        try:
            async with httpx.AsyncClient(timeout=10.0) as client:
                response = await client.get(
                    url,
                    headers=self._get_headers(),
                )

                if response.status_code != 200:
                    raise SystranError(
                        f"Failed to get supported languages: {response.text}",
                        status_code=response.status_code,
                    )

                return response.json().get("languagePairs", [])

        except httpx.RequestError as e:
            logger.error(f"SYSTRAN API error getting languages: {e}")
            raise SystranError(f"Failed to get supported languages: {e}") from e
