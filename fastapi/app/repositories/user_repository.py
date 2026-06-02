from typing import Optional, List
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession
from app.models.user import User, Role

class UserRepository:
    def __init__(self, db: AsyncSession):
        self.db = db

    async def get_by_username(self, username: str) -> Optional[User]:
        query = select(User).where(User.username == username, User.is_deleted == False)
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def get_by_email(self, email: str) -> Optional[User]:
        query = select(User).where(User.email == email, User.is_deleted == False)
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def create(self, user: User) -> User:
        self.db.add(user)
        await self.db.flush()
        return user

    async def get_by_id(self, user_id: str) -> Optional[User]:
        query = select(User).where(User.id == user_id, User.is_deleted == False)
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def get_by_id_any(self, user_id: str) -> Optional[User]:
        query = select(User).where(User.id == user_id)
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def update(self, user: User) -> User:
        self.db.add(user)
        await self.db.flush()
        return user

    async def list(self, skip: int = 0, limit: int = 100) -> List[User]:
        query = select(User).where(User.is_deleted == False).offset(skip).limit(limit)
        result = await self.db.execute(query)
        return result.scalars().all()

    async def soft_delete(self, user: User) -> User:
        from datetime import datetime, timezone
        user.is_deleted = True
        user.deleted_at = datetime.now(timezone.utc)
        self.db.add(user)
        await self.db.flush()
        return user

    async def hard_delete(self, user: User) -> None:
        await self.db.delete(user)
        await self.db.flush()

class RoleRepository:
    def __init__(self, db: AsyncSession):
        self.db = db

    async def get_by_name(self, name: str) -> Optional[Role]:
        query = select(Role).where(Role.name == name, Role.is_active == True)
        result = await self.db.execute(query)
        return result.scalar_one_or_none()
