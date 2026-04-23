"""Tests for EPO XML parsers.

Uses inline XML fixtures to keep tests self-contained. Each parser is
exercised against a realistic response and against error paths (malformed
XML, missing required elements).
"""

from __future__ import annotations

from datetime import date

import pytest

from app.infrastructure.epo.exceptions import EpoParsingError
from app.infrastructure.epo.schemas import (
    parse_abstract_response,
    parse_biblio_response,
    parse_family_response,
    parse_legal_response,
    parse_search_response,
)

SEARCH_XML = b"""<?xml version="1.0" encoding="UTF-8"?>
<ops:world-patent-data xmlns:ops="http://ops.epo.org" xmlns:ex="http://www.epo.org/exchange">
  <ops:biblio-search total-result-count="2">
    <ops:search-result>
      <ops:publication-reference>
        <ex:document-id document-id-type="docdb">
          <ex:country>EP</ex:country>
          <ex:doc-number>1000000</ex:doc-number>
          <ex:kind>A1</ex:kind>
          <ex:date>20200102</ex:date>
        </ex:document-id>
      </ops:publication-reference>
    </ops:search-result>
    <ops:search-result>
      <ops:publication-reference>
        <ex:document-id document-id-type="docdb">
          <ex:country>US</ex:country>
          <ex:doc-number>20210123456</ex:doc-number>
          <ex:kind>A1</ex:kind>
          <ex:date>20210301</ex:date>
        </ex:document-id>
      </ops:publication-reference>
    </ops:search-result>
  </ops:biblio-search>
</ops:world-patent-data>
"""


BIBLIO_XML = """<?xml version="1.0" encoding="UTF-8"?>
<ops:world-patent-data xmlns:ops="http://ops.epo.org" xmlns:ex="http://www.epo.org/exchange">
  <ex:exchange-documents>
    <ex:exchange-document country="EP" doc-number="1000000" kind="A1">
      <ex:bibliographic-data>
        <ex:publication-reference>
          <ex:document-id><ex:date>20200102</ex:date></ex:document-id>
        </ex:publication-reference>
        <ex:application-reference>
          <ex:document-id><ex:date>20180615</ex:date></ex:document-id>
        </ex:application-reference>
        <ex:classifications-ipcr>
          <ex:classification-ipc><ex:text>G06F 17/30</ex:text></ex:classification-ipc>
          <ex:classification-ipc><ex:text>G06N 3/08</ex:text></ex:classification-ipc>
        </ex:classifications-ipcr>
        <ex:parties>
          <ex:applicants>
            <ex:applicant>
              <ex:applicant-name><ex:name>Acme Corporation</ex:name></ex:applicant-name>
            </ex:applicant>
            <ex:applicant>
              <ex:applicant-name><ex:name>Globex S.A.</ex:name></ex:applicant-name>
            </ex:applicant>
          </ex:applicants>
          <ex:inventors>
            <ex:inventor>
              <ex:inventor-name><ex:name>Smith, John</ex:name></ex:inventor-name>
            </ex:inventor>
          </ex:inventors>
        </ex:parties>
        <ex:invention-title lang="en">A method for doing something</ex:invention-title>
        <ex:invention-title lang="fr">Un proc\u00e9d\u00e9 pour faire quelque chose</ex:invention-title>
      </ex:bibliographic-data>
    </ex:exchange-document>
  </ex:exchange-documents>
</ops:world-patent-data>
""".encode()


ABSTRACT_XML = b"""<?xml version="1.0" encoding="UTF-8"?>
<ops:world-patent-data xmlns:ops="http://ops.epo.org" xmlns:ex="http://www.epo.org/exchange">
  <ex:exchange-documents>
    <ex:exchange-document country="EP" doc-number="1000000" kind="A1">
      <ex:abstract lang="en">
        <ex:p>A method for processing data efficiently.</ex:p>
        <ex:p>The method comprises several steps.</ex:p>
      </ex:abstract>
    </ex:exchange-document>
  </ex:exchange-documents>
</ops:world-patent-data>
"""


FAMILY_XML = b"""<?xml version="1.0" encoding="UTF-8"?>
<ops:world-patent-data xmlns:ops="http://ops.epo.org" xmlns:ex="http://www.epo.org/exchange">
  <ops:patent-family>
    <ops:publication-reference>
      <ex:document-id document-id-type="docdb">
        <ex:country>EP</ex:country>
        <ex:doc-number>1000000</ex:doc-number>
        <ex:kind>A1</ex:kind>
      </ex:document-id>
    </ops:publication-reference>
    <ops:family-member>
      <ex:publication-reference>
        <ex:document-id document-id-type="docdb">
          <ex:country>US</ex:country>
          <ex:doc-number>20200111222</ex:doc-number>
          <ex:kind>A1</ex:kind>
        </ex:document-id>
      </ex:publication-reference>
    </ops:family-member>
    <ops:family-member>
      <ex:publication-reference>
        <ex:document-id document-id-type="docdb">
          <ex:country>JP</ex:country>
          <ex:doc-number>2020100000</ex:doc-number>
          <ex:kind>A</ex:kind>
        </ex:document-id>
      </ex:publication-reference>
    </ops:family-member>
  </ops:patent-family>
</ops:world-patent-data>
"""


LEGAL_XML = b"""<?xml version="1.0" encoding="UTF-8"?>
<ops:world-patent-data xmlns:ops="http://ops.epo.org" xmlns:ex="http://www.epo.org/exchange">
  <ops:patent-family legal="true" total-result-count="1">
    <ops:publication-reference>
      <ex:document-id document-id-type="docdb">
        <ex:country>EP</ex:country>
        <ex:doc-number>1000000</ex:doc-number>
        <ex:kind>A1</ex:kind>
      </ex:document-id>
    </ops:publication-reference>
    <ops:family-member family-id="94475802">
      <ops:legal code="PGFP" desc="FEE PAYMENT" infl="+" dateMigr="00010101">
        <ops:L007EP desc="Gazette DATE">2020-06-01</ops:L007EP>
      </ops:legal>
      <ops:legal code="PG25" desc="LAPSED IN A CONTRACTING STATE" infl="-" dateMigr="00010101">
        <ops:L007EP desc="Gazette DATE">2021-01-15</ops:L007EP>
      </ops:legal>
    </ops:family-member>
  </ops:patent-family>
</ops:world-patent-data>
"""


LEGAL_XML_LEGACY_FALLBACK = b"""<?xml version="1.0" encoding="UTF-8"?>
<ops:world-patent-data xmlns:ops="http://ops.epo.org" xmlns:ex="http://www.epo.org/exchange">
  <ops:patent-family legal="true">
    <ops:publication-reference>
      <ex:document-id document-id-type="docdb">
        <ex:country>EP</ex:country>
        <ex:doc-number>2000000</ex:doc-number>
        <ex:kind>A1</ex:kind>
      </ex:document-id>
    </ops:publication-reference>
    <ops:family-member>
      <ops:legal code="PGFP" date="20200601">
        <ops:text>Annual fee paid</ops:text>
      </ops:legal>
    </ops:family-member>
  </ops:patent-family>
</ops:world-patent-data>
"""


class TestSearchParser:
    def test_parses_all_entries(self):
        result = parse_search_response(SEARCH_XML)
        assert result.total_results == 2
        assert len(result.entries) == 2
        assert result.entries[0].doc_id == "EP.1000000.A1"
        assert result.entries[0].publication_date == date(2020, 1, 2)
        assert result.entries[1].doc_id == "US.20210123456.A1"

    def test_missing_biblio_search_raises(self):
        xml = b"""<?xml version="1.0"?><ops:world-patent-data xmlns:ops="http://ops.epo.org"/>"""
        with pytest.raises(EpoParsingError):
            parse_search_response(xml)

    def test_malformed_xml_raises(self):
        with pytest.raises(EpoParsingError):
            parse_search_response(b"not xml at all")


class TestBiblioParser:
    def test_parses_full_biblio(self):
        biblio = parse_biblio_response(BIBLIO_XML)
        assert biblio.doc_id == "EP.1000000.A1"
        assert biblio.title == "A method for doing something"  # English preferred
        assert biblio.applicants == ["Acme Corporation", "Globex S.A."]
        assert biblio.inventors == ["Smith, John"]
        assert biblio.ipc_classes == ["G06F 17/30", "G06N 3/08"]
        assert biblio.publication_date == date(2020, 1, 2)
        assert biblio.application_date == date(2018, 6, 15)

    def test_missing_exchange_document_raises(self):
        with pytest.raises(EpoParsingError):
            parse_biblio_response(b"<root/>")

    def test_missing_country_attribute_raises(self):
        xml = b"""<?xml version="1.0"?>
<ops:world-patent-data xmlns:ops="http://ops.epo.org" xmlns:ex="http://www.epo.org/exchange">
  <ex:exchange-documents>
    <ex:exchange-document kind="A1"/>
  </ex:exchange-documents>
</ops:world-patent-data>
"""
        with pytest.raises(EpoParsingError):
            parse_biblio_response(xml)


class TestAbstractParser:
    def test_parses_paragraphs(self):
        abstract = parse_abstract_response(ABSTRACT_XML)
        assert abstract.doc_id == "EP.1000000.A1"
        assert abstract.lang == "en"
        assert "A method for processing data efficiently." in abstract.text
        assert "The method comprises several steps." in abstract.text

    def test_missing_abstract_raises(self):
        xml = b"""<?xml version="1.0"?>
<ops:world-patent-data xmlns:ops="http://ops.epo.org" xmlns:ex="http://www.epo.org/exchange">
  <ex:exchange-documents>
    <ex:exchange-document country="EP" doc-number="1000000" kind="A1"/>
  </ex:exchange-documents>
</ops:world-patent-data>
"""
        with pytest.raises(EpoParsingError):
            parse_abstract_response(xml)


class TestFamilyParser:
    def test_parses_family_members(self):
        family = parse_family_response(FAMILY_XML)
        assert family.doc_id == "EP.1000000.A1"
        assert family.family_members == ["US.20200111222.A1", "JP.2020100000.A"]

    def test_missing_patent_family_raises(self):
        with pytest.raises(EpoParsingError):
            parse_family_response(b"<root/>")


class TestLegalParser:
    def test_parses_legal_events(self):
        legal = parse_legal_response(LEGAL_XML)
        assert legal.doc_id == "EP.1000000.A1"
        assert len(legal.events) == 2
        first = legal.events[0]
        assert first.code == "PGFP"
        assert first.event_date == date(2020, 6, 1)
        # Description comes from the @desc attribute (real EPO format).
        assert first.description == "FEE PAYMENT"
        second = legal.events[1]
        assert second.code == "PG25"
        assert second.event_date == date(2021, 1, 15)
        assert second.description == "LAPSED IN A CONTRACTING STATE"

    def test_falls_back_to_legacy_text_and_date_attrs(self):
        """Older fixtures use <ops:text> + @date — the parser still accepts them."""
        legal = parse_legal_response(LEGAL_XML_LEGACY_FALLBACK)
        assert legal.doc_id == "EP.2000000.A1"
        assert len(legal.events) == 1
        assert legal.events[0].code == "PGFP"
        assert legal.events[0].event_date == date(2020, 6, 1)
        assert legal.events[0].description == "Annual fee paid"

    def test_missing_patent_family_raises(self):
        with pytest.raises(EpoParsingError):
            parse_legal_response(b"<root/>")
