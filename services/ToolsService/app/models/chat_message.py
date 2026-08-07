from sqlalchemy import Column, String, Text, ForeignKey, Boolean
from sqlalchemy.dialects.postgresql import UUID
from sqlalchemy.orm import relationship
from app.db.base import Base

class ChatMessage(Base):
    """
    SQLAlchemy model representing a chat message in a chat room.
    """
    room_id = Column(UUID(as_uuid=True), ForeignKey("chat_rooms.id", ondelete="CASCADE"), nullable=False, index=True)
    sender_name = Column(String(100), nullable=False)
    sender_avatar = Column(String(50), nullable=True)
    sender_color = Column(String(100), nullable=True)
    text = Column(Text, nullable=False)
    is_user = Column(Boolean, default=False, nullable=False)

    room = relationship("ChatRoom", back_populates="messages")
