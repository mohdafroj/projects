from typing import Optional, Any
from uuid import UUID
from datetime import datetime
from pydantic import BaseModel, ConfigDict

class AuditLogBase(BaseModel):
    user_id: Optional[UUID] = None
    action: str
    resource: str
    status_code: int
    request_id: Optional[str] = None
    ip_address: Optional[str] = None
    user_agent: Optional[str] = None
    metadata_json: Optional[Any] = None
    description: Optional[str] = None

class AuditLogResponse(AuditLogBase):
    id: UUID
    created_at: datetime
    
    model_config = ConfigDict(from_attributes=True)
