from typing import Any, List
from fastapi import APIRouter, Depends, Query
from sqlalchemy.ext.asyncio import AsyncSession
from app.db.session import get_db
from app.schemas.audit import AuditLogResponse
from app.schemas.response import IResponse
from app.services.audit_service import AuditService
from app.api.dependencies.auth import require_permission
from app.models.user import User

router = APIRouter()

@router.get("/", response_model=IResponse[List[AuditLogResponse]])
async def list_audit_logs(
    db: AsyncSession = Depends(get_db),
    skip: int = Query(0, ge=0),
    limit: int = Query(10, ge=1, le=100),
    current_user: User = Depends(require_permission("audit:view"))
) -> Any:
    """
    Retrieve system audit logs with pagination.
    Requires 'audit:view' permission.
    """
    audit_service = AuditService(db)
    logs = await audit_service.list_logs(skip=skip, limit=limit)
    return IResponse(
        success=True,
        message="Audit logs retrieved successfully",
        data=logs
    )
