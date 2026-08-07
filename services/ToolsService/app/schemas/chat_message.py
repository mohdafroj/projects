from pydantic import BaseModel, Field, ConfigDict
from uuid import UUID
from datetime import datetime
from typing import Optional

class ChatMessageBase(BaseModel):
    sender_name: str = Field(..., max_length=100)
    sender_avatar: Optional[str] = Field(None, max_length=50)
    sender_color: Optional[str] = Field(None, max_length=100)
    text: str
    is_user: bool = False

class ChatMessageCreate(ChatMessageBase):
    pass

class ChatMessageResponse(ChatMessageBase):
    id: UUID
    room_id: UUID
    created_at: datetime
    updated_at: datetime

    model_config = ConfigDict(from_attributes=True)
