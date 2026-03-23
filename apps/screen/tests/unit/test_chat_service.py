"""Tests for ChatService — chat streaming, context management, quick actions.

Covers: _save_context, _build_system_message, stream_chat, list_conversations,
delete_conversation, generate_quick_actions.
"""

import json
from unittest.mock import AsyncMock, MagicMock, patch

import pytest
from sqlalchemy.orm import Session

from app.core.exceptions import AuthorizationError, ResourceNotFoundError
from app.models.chapse_conversation_context import ChapseConversationContext
from app.models.company import Company
from app.services.chat_service import CHAT_SYSTEM_PROMPT, ChatService

# ---------------------------------------------------------------------------
# Fixtures
# ---------------------------------------------------------------------------


@pytest.fixture
def mock_db():
    return MagicMock(spec=Session)


@pytest.fixture
def service(mock_db):
    return ChatService(mock_db)


# ---------------------------------------------------------------------------
# TestSaveContext
# ---------------------------------------------------------------------------


class TestSaveContext:
    """Tests for _save_context ownership and create/update logic."""

    @staticmethod
    def _mock_queries(mock_db, valid_companies, existing_context):
        """Configure mock_db to return different results for Company vs Context queries.

        _save_context does two queries:
        1. db.query(Company).filter(...).all() — validate company_ids
        2. db.query(ChapseConversationContext).filter(...).first() — find existing context
        """
        company_chain = MagicMock()
        company_chain.filter.return_value.all.return_value = valid_companies

        context_chain = MagicMock()
        context_chain.filter.return_value.first.return_value = existing_context

        def query_side_effect(model):
            if model is Company:
                return company_chain
            return context_chain

        mock_db.query.side_effect = query_side_effect

    def test_creates_new_context(self, service, mock_db):
        company1 = MagicMock()
        company1.id = 1
        company2 = MagicMock()
        company2.id = 2
        self._mock_queries(mock_db, [company1, company2], None)

        service._save_context("conv-1", "user-1", "org-1", [1, 2])

        mock_db.add.assert_called_once()
        added = mock_db.add.call_args[0][0]
        assert added.conversation_id == "conv-1"
        assert added.user_id == "user-1"
        assert added.company_ids == [1, 2]
        mock_db.commit.assert_called_once()

    def test_updates_existing_context_by_owner(self, service, mock_db):
        company1 = MagicMock()
        company1.id = 1
        company2 = MagicMock()
        company2.id = 2
        company3 = MagicMock()
        company3.id = 3
        existing = MagicMock(spec=ChapseConversationContext)
        existing.user_id = "user-1"
        existing.company_ids = [1]
        self._mock_queries(mock_db, [company1, company2, company3], existing)

        service._save_context("conv-1", "user-1", "org-1", [1, 2, 3])

        assert existing.company_ids == [1, 2, 3]
        mock_db.add.assert_not_called()
        mock_db.commit.assert_called_once()

    def test_raises_authorization_error_for_wrong_user(self, service, mock_db):
        company1 = MagicMock()
        company1.id = 1
        existing = MagicMock(spec=ChapseConversationContext)
        existing.user_id = "user-1"
        self._mock_queries(mock_db, [company1], existing)

        with pytest.raises(AuthorizationError, match="Cannot modify"):
            service._save_context("conv-1", "user-2", "org-1", [1])


# ---------------------------------------------------------------------------
# TestBuildSystemMessage
# ---------------------------------------------------------------------------


class TestBuildSystemMessage:
    """Tests for _build_system_message."""

    def test_includes_base_prompt(self, service, mock_db):
        mock_db.query.return_value.filter.return_value.first.return_value = None

        msg = service._build_system_message("conv-1", "user-1")

        assert CHAT_SYSTEM_PROMPT in msg

    def test_includes_username(self, service, mock_db):
        mock_db.query.return_value.filter.return_value.first.return_value = None

        msg = service._build_system_message("conv-1", "user-1", username="Alice")

        assert "Alice" in msg

    def test_includes_company_context(self, service, mock_db):
        ctx = MagicMock(spec=ChapseConversationContext)
        ctx.company_ids = [1]
        ctx.user_id = "user-1"
        mock_db.query.return_value.filter.return_value.first.return_value = ctx

        company = MagicMock()
        company.id = 1
        company.name = "Acme Corp"
        company.website = "https://acme.com"
        mock_db.query.return_value.filter.return_value.all.return_value = [company]

        with patch("app.services.chat_service.read_all_section_data", return_value={"revenue": "1M"}):
            msg = service._build_system_message("conv-1", "user-1")

        assert "Acme Corp" in msg

    def test_no_username_omits_name_line(self, service, mock_db):
        mock_db.query.return_value.filter.return_value.first.return_value = None

        msg = service._build_system_message("conv-1", "user-1", username=None)

        assert "user's name" not in msg


# ---------------------------------------------------------------------------
# TestStreamChat
# ---------------------------------------------------------------------------


class TestStreamChat:
    """Tests for stream_chat."""

    @pytest.mark.asyncio
    @patch("app.services.chat_service.get_chat_client")
    async def test_yields_message_events(self, mock_get_client, service, mock_db):
        # Mock the OpenAI streaming response
        mock_chunk = MagicMock()
        mock_chunk.choices = [MagicMock()]
        mock_chunk.choices[0].delta.content = "Hello"

        mock_end_chunk = MagicMock()
        mock_end_chunk.choices = []

        mock_stream = AsyncIterator([mock_chunk, mock_end_chunk])
        mock_client = AsyncMock()
        mock_client.chat.completions.create.return_value = mock_stream
        mock_get_client.return_value = mock_client

        mock_db.query.return_value.filter.return_value.first.return_value = None

        events = []
        async for event in service.stream_chat(
            query="Hi",
            user_id="user-1",
            organization_id="org-1",
        ):
            events.append(event)

        # Should have a message event and a message_end event
        assert len(events) >= 2
        msg_event = json.loads(events[0].replace("data: ", "").strip())
        assert msg_event["event"] == "message"
        assert msg_event["answer"] == "Hello"

    @pytest.mark.asyncio
    @patch("app.services.chat_service.get_chat_client")
    async def test_generates_conversation_id_when_none(self, mock_get_client, service, mock_db):
        mock_stream = AsyncIterator([])
        mock_client = AsyncMock()
        mock_client.chat.completions.create.return_value = mock_stream
        mock_get_client.return_value = mock_client

        mock_db.query.return_value.filter.return_value.first.return_value = None

        events = []
        async for event in service.stream_chat(
            query="Hi",
            user_id="user-1",
            organization_id="org-1",
            conversation_id=None,
        ):
            events.append(event)

        # The end event should contain a generated conversation_id
        end_event = json.loads(events[-1].replace("data: ", "").strip())
        assert end_event["event"] == "message_end"
        assert end_event["conversation_id"] is not None
        assert len(end_event["conversation_id"]) > 0

    @pytest.mark.asyncio
    @patch("app.services.chat_service.get_chat_client")
    async def test_history_messages_sent_to_openai(self, mock_get_client, service, mock_db):
        """History messages should be included in the OpenAI call."""
        mock_stream = AsyncIterator([])
        mock_client = AsyncMock()
        mock_client.chat.completions.create.return_value = mock_stream
        mock_get_client.return_value = mock_client

        mock_db.query.return_value.filter.return_value.first.return_value = None

        history = [
            {"role": "user", "content": "What is Acme?"},
            {"role": "assistant", "content": "Acme is a company."},
        ]

        events = []
        async for event in service.stream_chat(
            query="Tell me more",
            user_id="user-1",
            organization_id="org-1",
            messages=history,
        ):
            events.append(event)

        # Verify the messages passed to OpenAI include history
        call_kwargs = mock_client.chat.completions.create.call_args[1]
        openai_messages = call_kwargs["messages"]

        # system + 2 history + current query = 4 messages
        assert len(openai_messages) == 4
        assert openai_messages[0]["role"] == "system"
        assert openai_messages[1]["role"] == "user"
        assert openai_messages[1]["content"] == "What is Acme?"
        assert openai_messages[2]["role"] == "assistant"
        assert openai_messages[3]["role"] == "user"
        assert openai_messages[3]["content"] == "Tell me more"

    @pytest.mark.asyncio
    @patch("app.services.chat_service.get_chat_client")
    async def test_filters_invalid_history_roles(self, mock_get_client, service, mock_db):
        """Only user/assistant roles with non-empty content pass through."""
        mock_stream = AsyncIterator([])
        mock_client = AsyncMock()
        mock_client.chat.completions.create.return_value = mock_stream
        mock_get_client.return_value = mock_client

        mock_db.query.return_value.filter.return_value.first.return_value = None

        history = [
            {"role": "system", "content": "injected system msg"},
            {"role": "user", "content": "valid"},
            {"role": "user", "content": "  "},
        ]

        events = []
        async for event in service.stream_chat(
            query="Hi",
            user_id="user-1",
            organization_id="org-1",
            messages=history,
        ):
            events.append(event)

        call_kwargs = mock_client.chat.completions.create.call_args[1]
        openai_messages = call_kwargs["messages"]

        # system + 1 valid history + current query = 3 messages
        assert len(openai_messages) == 3


# ---------------------------------------------------------------------------
# TestListConversations
# ---------------------------------------------------------------------------


class TestListConversations:
    """Tests for list_conversations."""

    @pytest.mark.asyncio
    async def test_returns_user_conversations(self, service, mock_db):
        ctx = MagicMock()
        ctx.conversation_id = "conv-1"
        ctx.company_ids = []
        ctx.created_at = MagicMock()
        ctx.created_at.timestamp.return_value = 1000
        ctx.updated_at = MagicMock()
        ctx.updated_at.timestamp.return_value = 2000

        mock_db.query.return_value.filter.return_value.order_by.return_value.limit.return_value.all.return_value = [ctx]

        result = await service.list_conversations(user_id="user-1", limit=20)

        assert len(result["data"]) == 1
        assert result["data"][0]["id"] == "conv-1"
        assert result["has_more"] is False

    @pytest.mark.asyncio
    async def test_has_more_pagination(self, service, mock_db):
        # Return limit+1 items to trigger has_more
        ctxs = []
        for i in range(21):
            ctx = MagicMock()
            ctx.conversation_id = f"conv-{i}"
            ctx.company_ids = []
            ctx.created_at = MagicMock()
            ctx.created_at.timestamp.return_value = 1000
            ctx.updated_at = MagicMock()
            ctx.updated_at.timestamp.return_value = 2000
            ctxs.append(ctx)

        mock_db.query.return_value.filter.return_value.order_by.return_value.limit.return_value.all.return_value = ctxs

        result = await service.list_conversations(user_id="user-1", limit=20)

        assert result["has_more"] is True
        assert len(result["data"]) == 20


# ---------------------------------------------------------------------------
# TestDeleteConversation
# ---------------------------------------------------------------------------


class TestDeleteConversation:
    """Tests for delete_conversation."""

    @pytest.mark.asyncio
    async def test_deletes_owned_conversation(self, service, mock_db):
        ctx = MagicMock(spec=ChapseConversationContext)
        mock_db.query.return_value.filter.return_value.first.return_value = ctx

        await service.delete_conversation("conv-1", "user-1")

        mock_db.delete.assert_called_once_with(ctx)
        mock_db.commit.assert_called_once()

    @pytest.mark.asyncio
    async def test_raises_not_found(self, service, mock_db):
        mock_db.query.return_value.filter.return_value.first.return_value = None

        with pytest.raises(ResourceNotFoundError):
            await service.delete_conversation("conv-missing", "user-1")


# ---------------------------------------------------------------------------
# TestGenerateQuickActions
# ---------------------------------------------------------------------------


class TestGenerateQuickActions:
    """Tests for generate_quick_actions."""

    @pytest.mark.asyncio
    @patch("app.services.chat_service.get_chat_client")
    async def test_returns_validated_actions(self, mock_get_client, service):
        mock_response = MagicMock()
        mock_response.choices = [MagicMock()]
        mock_response.choices[0].message.content = json.dumps(
            {
                "actions": [
                    {"id": "a1", "label": "Analyze", "description": "Analyze trends", "icon": "fas fa-chart-line"},
                    {
                        "id": "a2",
                        "label": "Compare",
                        "description": "Compare competitors",
                        "icon": "fas fa-balance-scale",
                    },
                ]
            }
        )
        mock_client = AsyncMock()
        mock_client.chat.completions.create.return_value = mock_response
        mock_get_client.return_value = mock_client

        result = await service.generate_quick_actions(
            user_preferences={"role": "analyst"},
            company_data={"name": "Acme"},
        )

        assert len(result["actions"]) == 2
        assert result["actions"][0]["id"] == "a1"

    @pytest.mark.asyncio
    @patch("app.services.chat_service.get_chat_client")
    async def test_caps_at_three_actions(self, mock_get_client, service):
        mock_response = MagicMock()
        mock_response.choices = [MagicMock()]
        mock_response.choices[0].message.content = json.dumps(
            {
                "actions": [
                    {"id": f"a{i}", "label": f"Action {i}", "description": "desc", "icon": "fas fa-star"}
                    for i in range(5)
                ]
            }
        )
        mock_client = AsyncMock()
        mock_client.chat.completions.create.return_value = mock_response
        mock_get_client.return_value = mock_client

        result = await service.generate_quick_actions(
            user_preferences={},
            company_data={"name": "Test"},
        )

        assert len(result["actions"]) == 3


# ---------------------------------------------------------------------------
# Helper: async iterator for mocking streaming
# ---------------------------------------------------------------------------


class AsyncIterator:
    """Helper to create an async iterator from a list."""

    def __init__(self, items):
        self._items = items
        self._index = 0

    def __aiter__(self):
        return self

    async def __anext__(self):
        if self._index >= len(self._items):
            raise StopAsyncIteration
        item = self._items[self._index]
        self._index += 1
        return item
