"""Pydantic schemas and XML parsers for EPO OPS responses.

The EPO OPS API returns XML with multiple namespaces (`http://ops.epo.org`,
`http://www.epo.org/exchange`, `http://www.epo.org/fulltext`). Each public
parser function accepts raw XML bytes and returns a Pydantic model, raising
`EpoParsingError` when required elements are missing or the payload is not
well-formed.
"""

from __future__ import annotations

from datetime import date, datetime

from lxml import etree
from pydantic import BaseModel, Field

from .exceptions import EpoParsingError

EPO_NS = {
    "ops": "http://ops.epo.org",
    "ex": "http://www.epo.org/exchange",
    "ftxt": "http://www.epo.org/fulltext",
}


class PatentSearchEntry(BaseModel):
    """A single hit in an EPO `published-data/search` response."""

    doc_id: str
    applicant_names: list[str] = Field(default_factory=list)
    title: str | None = None
    publication_date: date | None = None


class PatentSearchResult(BaseModel):
    """Result of `search_patents(applicant_name)`."""

    total_results: int = 0
    entries: list[PatentSearchEntry] = Field(default_factory=list)


class PatentBiblio(BaseModel):
    """Bibliographic data for a single patent publication."""

    doc_id: str
    title: str | None = None
    applicants: list[str] = Field(default_factory=list)
    inventors: list[str] = Field(default_factory=list)
    publication_date: date | None = None
    application_date: date | None = None


class PatentAbstract(BaseModel):
    """Abstract text for a single patent publication."""

    doc_id: str
    text: str
    lang: str | None = None


class PatentFamily(BaseModel):
    """Simple patent-family summary: the seed doc and its family members."""

    doc_id: str
    family_members: list[str] = Field(default_factory=list)


class PatentLegalEvent(BaseModel):
    """One entry in the legal-status history of a publication."""

    code: str
    event_date: date | None = None
    description: str | None = None


class PatentLegalStatus(BaseModel):
    """Legal-status history of a single patent publication."""

    doc_id: str
    events: list[PatentLegalEvent] = Field(default_factory=list)


def _parse_root(xml: bytes) -> etree._Element:
    try:
        return etree.fromstring(xml)
    except etree.XMLSyntaxError as exc:
        raise EpoParsingError(
            message=f"Malformed EPO XML: {exc}",
            response_body=_safe_preview(xml),
        ) from exc


def _safe_preview(xml: bytes) -> str | None:
    try:
        return xml.decode("utf-8", errors="replace")[:500]
    except Exception:
        return None


def _parse_epo_date(raw: str | None) -> date | None:
    if not raw:
        return None
    try:
        return datetime.strptime(raw, "%Y%m%d").date()
    except ValueError:
        try:
            return datetime.strptime(raw, "%Y-%m-%d").date()
        except ValueError:
            return None


def _build_doc_id(doc_ref: etree._Element | None) -> str | None:
    """Compose `CC.NNNN.KK` style doc_id from a <document-id> element."""
    if doc_ref is None:
        return None
    country = doc_ref.findtext("ex:country", namespaces=EPO_NS)
    number = doc_ref.findtext("ex:doc-number", namespaces=EPO_NS)
    kind = doc_ref.findtext("ex:kind", namespaces=EPO_NS)
    if not country or not number:
        return None
    return f"{country}.{number}.{kind}" if kind else f"{country}.{number}"


def parse_search_response(xml: bytes) -> PatentSearchResult:
    """Parse `published-data/search` XML into a `PatentSearchResult`."""
    root = _parse_root(xml)

    biblio_search = root.find(".//ops:biblio-search", namespaces=EPO_NS)
    if biblio_search is None:
        raise EpoParsingError(
            message="Missing <biblio-search> element in EPO search response",
            response_body=_safe_preview(xml),
        )

    total_raw = biblio_search.get("total-result-count", "0")
    try:
        total = int(total_raw)
    except ValueError:
        total = 0

    entries: list[PatentSearchEntry] = []
    for ref in biblio_search.findall(".//ops:search-result/ops:publication-reference", namespaces=EPO_NS):
        doc_ref = ref.find("ex:document-id[@document-id-type='docdb']", namespaces=EPO_NS)
        if doc_ref is None:
            doc_ref = ref.find("ex:document-id", namespaces=EPO_NS)
        doc_id = _build_doc_id(doc_ref)
        if not doc_id:
            continue
        pub_date_raw = doc_ref.findtext("ex:date", namespaces=EPO_NS) if doc_ref is not None else None
        entries.append(
            PatentSearchEntry(
                doc_id=doc_id,
                publication_date=_parse_epo_date(pub_date_raw),
            )
        )

    return PatentSearchResult(total_results=total, entries=entries)


def parse_biblio_response(xml: bytes) -> PatentBiblio:
    """Parse `publication/docdb/{doc_id}/biblio` XML into `PatentBiblio`."""
    root = _parse_root(xml)

    exch_doc = root.find(".//ex:exchange-document", namespaces=EPO_NS)
    if exch_doc is None:
        raise EpoParsingError(
            message="Missing <exchange-document> element in biblio response",
            response_body=_safe_preview(xml),
        )

    country = exch_doc.get("country")
    number = exch_doc.get("doc-number")
    kind = exch_doc.get("kind")
    if not country or not number:
        raise EpoParsingError(
            message="<exchange-document> missing country/doc-number attribute",
            response_body=_safe_preview(xml),
        )
    doc_id = f"{country}.{number}.{kind}" if kind else f"{country}.{number}"

    title_el = exch_doc.find(".//ex:invention-title[@lang='en']", namespaces=EPO_NS)
    if title_el is None:
        title_el = exch_doc.find(".//ex:invention-title", namespaces=EPO_NS)
    title = title_el.text.strip() if title_el is not None and title_el.text else None

    applicants = [
        (el.text or "").strip()
        for el in exch_doc.findall(".//ex:applicant/ex:applicant-name/ex:name", namespaces=EPO_NS)
        if el.text
    ]

    inventors = [
        (el.text or "").strip()
        for el in exch_doc.findall(".//ex:inventor/ex:inventor-name/ex:name", namespaces=EPO_NS)
        if el.text
    ]

    pub_date = _parse_epo_date(exch_doc.findtext(".//ex:publication-reference//ex:date", namespaces=EPO_NS))
    app_date = _parse_epo_date(exch_doc.findtext(".//ex:application-reference//ex:date", namespaces=EPO_NS))

    return PatentBiblio(
        doc_id=doc_id,
        title=title,
        applicants=applicants,
        inventors=inventors,
        publication_date=pub_date,
        application_date=app_date,
    )


def parse_abstract_response(xml: bytes) -> PatentAbstract:
    """Parse `publication/docdb/{doc_id}/abstract` XML into `PatentAbstract`."""
    root = _parse_root(xml)

    exch_doc = root.find(".//ex:exchange-document", namespaces=EPO_NS)
    if exch_doc is None:
        raise EpoParsingError(
            message="Missing <exchange-document> element in abstract response",
            response_body=_safe_preview(xml),
        )

    country = exch_doc.get("country") or ""
    number = exch_doc.get("doc-number") or ""
    kind = exch_doc.get("kind")
    doc_id = f"{country}.{number}.{kind}" if kind else f"{country}.{number}"

    abstract_el = exch_doc.find(".//ex:abstract", namespaces=EPO_NS)
    if abstract_el is None:
        raise EpoParsingError(
            message="Missing <abstract> element in abstract response",
            response_body=_safe_preview(xml),
        )

    lang = abstract_el.get("lang")
    paragraphs = [(p.text or "").strip() for p in abstract_el.findall("ex:p", namespaces=EPO_NS) if p.text]
    text = "\n".join(paragraphs) if paragraphs else (abstract_el.text or "").strip()

    return PatentAbstract(doc_id=doc_id, text=text, lang=lang)


def parse_family_response(xml: bytes) -> PatentFamily:
    """Parse `family/publication/docdb/{doc_id}` XML into `PatentFamily`."""
    root = _parse_root(xml)

    family = root.find(".//ops:patent-family", namespaces=EPO_NS)
    if family is None:
        raise EpoParsingError(
            message="Missing <patent-family> element in family response",
            response_body=_safe_preview(xml),
        )

    seed_ref = family.find(
        ".//ops:publication-reference/ex:document-id[@document-id-type='docdb']",
        namespaces=EPO_NS,
    )
    seed_id = _build_doc_id(seed_ref) or ""

    members: list[str] = []
    for member in family.findall(".//ops:family-member", namespaces=EPO_NS):
        doc_ref = member.find(
            "ex:publication-reference/ex:document-id[@document-id-type='docdb']",
            namespaces=EPO_NS,
        )
        if doc_ref is None:
            doc_ref = member.find("ex:publication-reference/ex:document-id", namespaces=EPO_NS)
        member_id = _build_doc_id(doc_ref)
        if member_id:
            members.append(member_id)

    return PatentFamily(doc_id=seed_id, family_members=members)


def parse_legal_response(xml: bytes) -> PatentLegalStatus:
    """Parse `legal/publication/docdb/{doc_id}` XML into `PatentLegalStatus`.

    EPO OPS wraps the legal history in ``<ops:patent-family legal="true">``
    (there is no ``<ops:legal-status>`` element in the real payload). The
    container groups ``<ops:legal>`` events under ``<ops:family-member>``
    children. Each event's code + description come from the ``code`` /
    ``desc`` attributes of the event element; the human date is the
    ``<ops:L007EP desc="Gazette DATE">YYYY-MM-DD</ops:L007EP>`` child
    rather than a top-level ``date`` attribute.
    """
    root = _parse_root(xml)

    family = root.find(".//ops:patent-family", namespaces=EPO_NS)
    if family is None:
        raise EpoParsingError(
            message="Missing <ops:patent-family> element in legal response",
            response_body=_safe_preview(xml),
        )

    doc_ref = family.find(
        "ops:publication-reference/ex:document-id[@document-id-type='docdb']",
        namespaces=EPO_NS,
    )
    doc_id = _build_doc_id(doc_ref) or ""

    events: list[PatentLegalEvent] = []
    for legal in family.findall(".//ops:legal", namespaces=EPO_NS):
        code = legal.get("code") or legal.findtext("ops:code", namespaces=EPO_NS) or ""
        if not code:
            continue
        event_date_raw = (
            legal.findtext("ops:L007EP", namespaces=EPO_NS)
            or legal.get("date")
            or legal.findtext("ops:date", namespaces=EPO_NS)
        )
        description = legal.get("desc") or legal.findtext("ops:text", namespaces=EPO_NS)
        events.append(
            PatentLegalEvent(
                code=code,
                event_date=_parse_epo_date(event_date_raw),
                description=description.strip() if description else None,
            )
        )

    return PatentLegalStatus(doc_id=doc_id, events=events)
