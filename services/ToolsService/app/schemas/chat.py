from pydantic import BaseModel, Field
from typing import List, Optional

class ChatMessage(BaseModel):
    role: str  # "user" or "assistant"
    content: str

class StreamChatRequest(BaseModel):
    prompt: str
    system_prompt: str
    model: str
    temperature: float = Field(default=0.7, ge=0.0, le=1.0)
    max_tokens: int = Field(default=2048, ge=256, le=4096)
    history: List[ChatMessage] = []
