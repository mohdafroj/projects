from typing import List
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession
from app.models.user import PasswordHistory

class PasswordHistoryRepository:
    def __init__(self, db: AsyncSession):
        self.db = db

    async def get_user_history(self, user_id: str, limit: int = 5) -> List[PasswordHistory]:
        query = select(PasswordHistory).where(PasswordHistory.user_id == user_id).order_by(PasswordHistory.created_at.desc()).limit(limit)
        result = await self.db.execute(query)
        return result.scalars().all()

    async def add_history(self, user_id: str, hashed_password: str) -> PasswordHistory:
        history = PasswordHistory(user_id=user_id, hashed_password=hashed_password)
        self.db.add(history)
        await self.db.flush()
        return history
