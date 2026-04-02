"""Pydantic schemas for WorldCheck One API requests and responses."""

from enum import StrEnum
from typing import Any

from pydantic import BaseModel, Field


class EntityType(StrEnum):
    """WorldCheck entity types for screening."""

    INDIVIDUAL = "INDIVIDUAL"
    ORGANISATION = "ORGANISATION"


class MatchStrength(StrEnum):
    """Confidence level of a screening match."""

    EXACT = "EXACT"
    STRONG = "STRONG"
    MEDIUM = "MEDIUM"
    WEAK = "WEAK"


class ScreeningRequest(BaseModel):
    """Request payload for synchronous screening.

    Used with POST /cases/screeningRequest endpoint.
    """

    groupId: str = Field(..., description="WorldCheck group ID for the screening")
    entityType: EntityType = Field(..., description="Type of entity to screen")
    caseId: str | None = Field(None, description="Optional client-provided case ID")
    name: str = Field(..., description="Name of the entity to screen")
    secondaryFields: list[dict] | None = Field(
        None,
        description="Additional fields (date of birth, nationality, etc.)",
    )
    providerTypes: list[str] = Field(
        default=["WATCHLIST"],
        description="Provider types to screen against",
    )


class ScreeningResultCategory(BaseModel):
    """Category associated with a screening match."""

    name: str = Field(..., description="Category name (e.g., Sanctions, PEP, Adverse Media)")


class ScreeningResultSource(BaseModel):
    """Source information for a screening match."""

    name: str | None = Field(None, description="Source name")
    type: str | None = Field(None, description="Source type")


class ReferenceProfile(BaseModel):
    """Detailed profile data from WorldCheck get_reference_profile endpoint.

    Contains the full compliance data: sanctions lists, PEP status,
    adverse media, country risks, aliases, etc.
    """

    referenceId: str = Field(..., description="Reference profile ID")
    name: str | None = Field(None, description="Primary name")
    entityType: str | None = Field(None, description="INDIVIDUAL or ORGANISATION")
    categories: list[dict[str, Any]] = Field(default_factory=list, description="Categories (Sanctions, PEP, etc.)")
    sources: list[dict[str, Any]] = Field(default_factory=list, description="Data sources with details")
    countryLinks: list[dict[str, Any]] = Field(default_factory=list, description="Country associations and roles")
    aliases: list[dict[str, Any]] = Field(default_factory=list, description="Known aliases / AKA names")
    events: list[dict[str, Any]] = Field(default_factory=list, description="Associated events (sanctions dates, etc.)")
    weblinks: list[dict[str, Any]] = Field(default_factory=list, description="Related web links / articles")

    model_config = {"extra": "allow"}


class ScreeningResult(BaseModel):
    """A single match result from a screening response."""

    referenceId: str = Field(..., description="Unique reference ID for the matched profile")
    matchStrength: MatchStrength | str = Field(..., description="Confidence level of the match")
    matchedTerm: str | None = Field(None, description="The term that was matched")
    submittedTerm: str | None = Field(None, description="The original submitted search term")
    matchedNameType: str | None = Field(None, description="Type of name matched (PRIMARY, AKA, etc.)")
    categories: list[str] = Field(default_factory=list, description="Match categories (Sanctions, PEP, etc.)")
    sources: list[str] = Field(default_factory=list, description="Data sources for the match")
    primaryName: str | None = Field(None, description="Primary name of the matched entity")
    gender: str | None = Field(None, description="Gender (for individuals)")
    events: list[dict] | None = Field(None, description="Associated events")
    profile: ReferenceProfile | None = Field(
        None, description="Enriched profile data (fetched for EXACT/STRONG matches)"
    )


class ScreeningResponse(BaseModel):
    """Response from a synchronous screening request.

    Contains the case information and all screening results.
    """

    caseSystemId: str = Field(..., description="System-generated case ID")
    caseId: str | None = Field(None, description="Client-provided case ID")
    name: str | None = Field(None, description="Screened entity name")
    results: list[ScreeningResult] = Field(default_factory=list, description="Screening match results")
    resultCount: int = Field(0, description="Total number of results")


class CaseResult(BaseModel):
    """Result entry from get_case_results endpoint."""

    resultId: str = Field(..., description="Result ID")
    referenceId: str = Field(..., description="Reference profile ID")
    matchStrength: MatchStrength | str = Field(..., description="Match confidence")
    matchedTerm: str | None = Field(None, description="Matched term")
    submittedTerm: str | None = Field(None, description="Submitted term")
    categories: list[str] = Field(default_factory=list, description="Categories")
    primaryName: str | None = Field(None, description="Primary name")
