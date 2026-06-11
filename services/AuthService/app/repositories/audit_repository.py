from typing import List
from sqlalchemy import select, desc
from sqlalchemy.ext.asyncio import AsyncSession
from app.models.audit import AuditLog

class AuditRepository:
    def __init__(self, db: AsyncSession):
        self.db = db

    async def create(self, audit_log: AuditLog) -> AuditLog:
        self.db.add(audit_log)
        await self.db.flush()
        return audit_log

    async def list(self, skip: int = 0, limit: int = 100) -> List[AuditLog]:
        query = select(AuditLog).order_by(desc(AuditLog.created_at)).offset(skip).limit(limit)
        result = await self.db.execute(query)
        return result.scalars().all()
