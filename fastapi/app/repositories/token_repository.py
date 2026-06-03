from typing import Optional
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession
from app.models.user import RefreshToken

class TokenRepository:
    def __init__(self, db: AsyncSession):
        self.db = db

    async def create(self, refresh_token: RefreshToken) -> RefreshToken:
        self.db.add(refresh_token)
        await self.db.flush()
        return refresh_token

    async def get_by_token(self, token: str) -> Optional[RefreshToken]:
        query = select(RefreshToken).where(
            RefreshToken.token == token, 
            RefreshToken.is_revoked == False
        )
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def revoke_token(self, refresh_token: RefreshToken) -> RefreshToken:
        refresh_token.is_revoked = True
        self.db.add(refresh_token)
        await self.db.flush()
        return refresh_token

    async def revoke_all_user_tokens(self, user_id: str) -> None:
        from sqlalchemy import update
        query = update(RefreshToken).where(
            RefreshToken.user_id == user_id,
            RefreshToken.is_revoked == False
        ).values(is_revoked=True)
        await self.db.execute(query)
        await self.db.flush()
