from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select
from uuid import UUID
from app.models.chat_message import ChatMessage
from app.schemas.chat_message import ChatMessageCreate

class ChatMessageRepository:
    async def get_by_room_id(self, db: AsyncSession, room_id: UUID, limit: int = 100) -> list[ChatMessage]:
        result = await db.execute(
            select(ChatMessage)
            .where(ChatMessage.room_id == room_id, ChatMessage.is_deleted == False)
            .order_by(ChatMessage.created_at.asc())
            .limit(limit)
        )
        return list(result.scalars().all())

    async def create(self, db: AsyncSession, message_data: ChatMessageCreate, room_id: UUID) -> ChatMessage:
        db_message = ChatMessage(room_id=room_id, **message_data.model_dump())
        db.add(db_message)
        await db.flush()
        return db_message
