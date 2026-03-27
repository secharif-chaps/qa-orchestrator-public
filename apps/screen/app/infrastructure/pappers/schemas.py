"""Pydantic schemas for Pappers API responses."""

from pydantic import BaseModel, Field


class PappersOfficer(BaseModel):
    """A company officer (dirigeant) from Pappers."""

    nom: str | None = None
    prenom: str | None = None
    qualite: str | None = None
    date_de_naissance: str | None = None

    model_config = {"extra": "allow"}


class PappersSearchResult(BaseModel):
    """Single result from Pappers search endpoint."""

    siren: str
    nom_entreprise: str | None = None
    siege: dict | None = None

    model_config = {"extra": "allow"}


class PappersCompanyData(BaseModel):
    """Full company data from Pappers entreprise endpoint."""

    siren: str
    nom_entreprise: str | None = None
    forme_juridique: str | None = None
    capital: float | None = None
    date_creation: str | None = None
    tranche_effectif: str | None = None
    code_naf: str | None = None
    libelle_code_naf: str | None = None
    siege: dict | None = None
    dirigeants: list[PappersOfficer] = Field(default_factory=list)
    # Financial data
    derniers_statuts: dict | None = None
    finances: list[dict] = Field(default_factory=list)

    model_config = {"extra": "allow"}
