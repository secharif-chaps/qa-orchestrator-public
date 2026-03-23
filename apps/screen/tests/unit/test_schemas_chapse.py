"""Tests for Chapse chatbot Pydantic schemas.

Covers: ChapseChatRequest, UpdateContextRequest, CompanySummary,
        ContextResponse, Conversation, ConversationsResponse,
        Message, ConversationDetailResponse, RenameRequest, RenameResponse,
        ChapseErrorResponse.
"""

import pytest
from pydantic import ValidationError

from app.schemas.chapse import (
    ChapseChatRequest,
    ChapseErrorResponse,
    CompanySummary,
    ContextResponse,
    Conversation,
    ConversationDetailResponse,
    ConversationsResponse,
    Message,
    RenameRequest,
    RenameResponse,
    UpdateContextRequest,
)

# ---------------------------------------------------------------------------
# ChapseChatRequest
# ---------------------------------------------------------------------------


class TestChapseChatRequest:
    def test_valid_query(self):
        req = ChapseChatRequest(query="Hello, how are you?")
        assert req.query == "Hello, how are you?"

    def test_empty_query_fails(self):
        with pytest.raises(ValidationError):
            ChapseChatRequest(query="")

    def test_query_max_length(self):
        """Query at exactly 4000 chars should pass."""
        req = ChapseChatRequest(query="a" * 4000)
        assert len(req.query) == 4000

    def test_query_over_max_length(self):
        with pytest.raises(ValidationError):
            ChapseChatRequest(query="a" * 4001)

    def test_company_ids_valid(self):
        req = ChapseChatRequest(query="test", company_ids=[1, 2, 3])
        assert req.company_ids == [1, 2, 3]

    def test_company_ids_max_three(self):
        with pytest.raises(ValidationError) as exc_info:
            ChapseChatRequest(query="test", company_ids=[1, 2, 3, 4])
        assert "Maximum 3 companies" in str(exc_info.value)

    def test_company_ids_none(self):
        req = ChapseChatRequest(query="test", company_ids=None)
        assert req.company_ids is None

    def test_company_ids_empty(self):
        req = ChapseChatRequest(query="test", company_ids=[])
        assert req.company_ids == []

    def test_conversation_id_optional(self):
        req = ChapseChatRequest(query="test")
        assert req.conversation_id is None

    def test_conversation_id_provided(self):
        req = ChapseChatRequest(query="test", conversation_id="conv-123")
        assert req.conversation_id == "conv-123"


# ---------------------------------------------------------------------------
# UpdateContextRequest
# ---------------------------------------------------------------------------


class TestUpdateContextRequest:
    def test_valid_company_ids(self):
        req = UpdateContextRequest(company_ids=[1, 2])
        assert req.company_ids == [1, 2]

    def test_max_three_companies(self):
        req = UpdateContextRequest(company_ids=[1, 2, 3])
        assert len(req.company_ids) == 3

    def test_over_three_companies_fails(self):
        with pytest.raises(ValidationError) as exc_info:
            UpdateContextRequest(company_ids=[1, 2, 3, 4])
        assert "Maximum 3 companies" in str(exc_info.value)

    def test_empty_list(self):
        req = UpdateContextRequest(company_ids=[])
        assert req.company_ids == []

    def test_default_empty(self):
        req = UpdateContextRequest()
        assert req.company_ids == []


# ---------------------------------------------------------------------------
# CompanySummary
# ---------------------------------------------------------------------------


class TestCompanySummary:
    def test_valid(self):
        cs = CompanySummary(id=1, name="Acme Corp")
        assert cs.id == 1
        assert cs.name == "Acme Corp"

    def test_missing_name(self):
        with pytest.raises(ValidationError):
            CompanySummary(id=1)

    def test_missing_id(self):
        with pytest.raises(ValidationError):
            CompanySummary(name="Acme")


# ---------------------------------------------------------------------------
# ContextResponse
# ---------------------------------------------------------------------------


class TestContextResponse:
    def test_defaults(self):
        ctx = ContextResponse()
        assert ctx.company_ids == []
        assert ctx.companies == []

    def test_with_data(self):
        ctx = ContextResponse(
            company_ids=[1, 2],
            companies=[CompanySummary(id=1, name="A"), CompanySummary(id=2, name="B")],
        )
        assert len(ctx.company_ids) == 2
        assert len(ctx.companies) == 2


# ---------------------------------------------------------------------------
# Conversation / ConversationsResponse
# ---------------------------------------------------------------------------


class TestConversation:
    def test_valid(self):
        conv = Conversation(
            id="conv-1",
            name="My Chat",
            created_at=1700000000,
            updated_at=1700000001,
        )
        assert conv.id == "conv-1"
        assert conv.company_ids == []

    def test_with_companies(self):
        conv = Conversation(
            id="conv-2",
            name="Chat",
            created_at=1700000000,
            updated_at=1700000001,
            company_ids=[1],
            companies=[CompanySummary(id=1, name="X")],
        )
        assert len(conv.companies) == 1


class TestConversationsResponse:
    def test_valid(self):
        resp = ConversationsResponse(
            data=[
                Conversation(id="1", name="C1", created_at=0, updated_at=0),
            ],
            has_more=False,
            limit=10,
        )
        assert len(resp.data) == 1
        assert resp.has_more is False


# ---------------------------------------------------------------------------
# Message / ConversationDetailResponse
# ---------------------------------------------------------------------------


class TestMessage:
    def test_valid(self):
        msg = Message(id="msg-1", query="Hello", answer="Hi there", created_at=1700000000)
        assert msg.query == "Hello"
        assert msg.feedback is None

    def test_with_feedback(self):
        msg = Message(
            id="msg-2",
            query="Q",
            answer="A",
            created_at=0,
            feedback={"rating": "good"},
        )
        assert msg.feedback["rating"] == "good"


class TestConversationDetailResponse:
    def test_valid(self):
        resp = ConversationDetailResponse(
            id="conv-1",
            name="Chat",
            created_at=0,
            updated_at=0,
            messages=[Message(id="m1", query="Q", answer="A", created_at=0)],
        )
        assert len(resp.messages) == 1
        assert resp.has_more_messages is False

    def test_defaults(self):
        resp = ConversationDetailResponse(
            id="conv-1",
            name="Chat",
            created_at=0,
            updated_at=0,
        )
        assert resp.company_ids == []
        assert resp.companies == []
        assert resp.messages == []
        assert resp.has_more_messages is False


# ---------------------------------------------------------------------------
# RenameRequest / RenameResponse
# ---------------------------------------------------------------------------


class TestRenameRequest:
    def test_with_name(self):
        rr = RenameRequest(name="New Name")
        assert rr.name == "New Name"
        assert rr.auto_generate is False

    def test_auto_generate(self):
        rr = RenameRequest(auto_generate=True)
        assert rr.name is None
        assert rr.auto_generate is True

    def test_name_max_length(self):
        rr = RenameRequest(name="a" * 255)
        assert len(rr.name) == 255

    def test_name_over_max_length(self):
        with pytest.raises(ValidationError):
            RenameRequest(name="a" * 256)


class TestRenameResponse:
    def test_valid(self):
        rr = RenameResponse(id="conv-1", name="Renamed")
        assert rr.id == "conv-1"


# ---------------------------------------------------------------------------
# ChapseErrorResponse
# ---------------------------------------------------------------------------


class TestChapseErrorResponse:
    def test_with_code(self):
        err = ChapseErrorResponse(detail="Something failed", code="ERR_001")
        assert err.detail == "Something failed"
        assert err.code == "ERR_001"

    def test_without_code(self):
        err = ChapseErrorResponse(detail="Error")
        assert err.code is None
