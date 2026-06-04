from typing import List, Optional
from sqlalchemy.ext.asyncio import AsyncSession
from app.repositories.audit_repository import AuditRepository
from app.models.audit import AuditLog

class AuditService:
    def __init__(self, db: AsyncSession):
        self.audit_repo = AuditRepository(db)

    async def log_action(
        self, 
        user_id: str | None,
        action: str,
        resource: str,
        status_code: int,
        ip_address: str | None,
        user_agent: str | None,
        metadata_json: dict | None = None,
        description: str | None = None
    ) -> AuditLog:
        new_log = AuditLog(
            user_id=user_id,
            action=action,
            resource=resource,
            status_code=status_code,
            ip_address=ip_address,
            user_agent=user_agent,
            metadata_json=metadata_json,
            description=description
        )
        return await self.audit_repo.create(new_log)

    async def list_logs(self, skip: int = 0, limit: int = 100) -> List[AuditLog]:
        return await self.audit_repo.list(skip=skip, limit=limit)
