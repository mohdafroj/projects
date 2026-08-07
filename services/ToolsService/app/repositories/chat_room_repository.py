from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select
from uuid import UUID
from app.models.chat_room import ChatRoom
from app.schemas.chat_room import ChatRoomCreate

class ChatRoomRepository:
    async def get_all(self, db: AsyncSession) -> list[ChatRoom]:
        result = await db.execute(select(ChatRoom).where(ChatRoom.is_deleted == False))
        return list(result.scalars().all())

    async def get_by_id(self, db: AsyncSession, room_id: UUID) -> ChatRoom | None:
        result = await db.execute(
            select(ChatRoom).where(ChatRoom.id == room_id, ChatRoom.is_deleted == False)
        )
        return result.scalar_one_or_none()

    async def get_by_name(self, db: AsyncSession, name: str) -> ChatRoom | None:
        result = await db.execute(
            select(ChatRoom).where(ChatRoom.name == name, ChatRoom.is_deleted == False)
        )
        return result.scalar_one_or_none()

    async def create(self, db: AsyncSession, room_data: ChatRoomCreate) -> ChatRoom:
        db_room = ChatRoom(**room_data.model_dump())
        db.add(db_room)
        await db.flush()
        return db_room

    async def delete(self, db: AsyncSession, room_id: UUID) -> bool:
        db_room = await self.get_by_id(db, room_id)
        if db_room:
            db_room.is_deleted = True
            await db.flush()
            return True
        return False
