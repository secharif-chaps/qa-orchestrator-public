from datetime import datetime
from typing import Any

from pydantic import BaseModel, ConfigDict

from app.models.delivery import DeliveryStatus


class DeliveryRead(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    id: int
    stream_id: int
    event_id: int
    status: DeliveryStatus
    error_message: str | None
    response_metadata: dict[str, Any] | None
    delivered_at: datetime | None
    created_at: datetime
    attempt_count: int
    next_retry_at: datetime | None
