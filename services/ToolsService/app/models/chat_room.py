from sqlalchemy import Column, String, Text
from sqlalchemy.orm import relationship
from app.db.base import Base

class ChatRoom(Base):
    """
    SQLAlchemy model representing a chat room, which can be a public channel or a private direct message.
    """
    name = Column(String(100), nullable=False, index=True)
    type = Column(String(50), nullable=False)  # "channel" | "dm"
    avatar = Column(String(50), nullable=True)
    avatar_color = Column(String(100), nullable=True)
    description = Column(Text, nullable=True)

    messages = relationship("ChatMessage", back_populates="room", cascade="all, delete-orphan", lazy="selectin")
