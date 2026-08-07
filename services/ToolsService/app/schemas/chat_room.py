from pydantic import BaseModel, Field, ConfigDict
from uuid import UUID
from datetime import datetime
from typing import Optional

class ChatRoomBase(BaseModel):
    name: str = Field(..., max_length=100)
    type: str = Field(..., max_length=50)  # "channel" | "dm"
    avatar: Optional[str] = Field(None, max_length=50)
    avatar_color: Optional[str] = Field(None, max_length=100)
    description: Optional[str] = None

class ChatRoomCreate(ChatRoomBase):
    pass

class ChatRoomUpdate(BaseModel):
    name: Optional[str] = Field(None, max_length=100)
    avatar: Optional[str] = Field(None, max_length=50)
    avatar_color: Optional[str] = Field(None, max_length=100)
    description: Optional[str] = None

class ChatRoomResponse(ChatRoomBase):
    id: UUID
    created_at: datetime
    updated_at: datetime

    model_config = ConfigDict(from_attributes=True)
