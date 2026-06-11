import uuid
import re
from datetime import datetime, timezone
from sqlalchemy import Column, DateTime, Boolean, ForeignKey
from sqlalchemy.dialects.postgresql import UUID
from sqlalchemy.orm import DeclarativeBase, declared_attr

class Base(DeclarativeBase):
    id = Column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4, index=True)
    
    # Audit fields
    created_at = Column(DateTime(timezone=True), default=lambda: datetime.now(timezone.utc), nullable=False)
    updated_at = Column(DateTime(timezone=True), default=lambda: datetime.now(timezone.utc), onupdate=lambda: datetime.now(timezone.utc), nullable=False)
    deleted_at = Column(DateTime(timezone=True), nullable=True)
    is_deleted = Column(Boolean, default=False, nullable=False)
    
    # Audit trail (optional link to user)
    created_by = Column(UUID(as_uuid=True), nullable=True)
    updated_by = Column(UUID(as_uuid=True), nullable=True)

    @declared_attr
    def __tablename__(cls) -> str:
        """
        Automatically converts CamelCase class names to plural snake_case table names.
        Example: AuditLog -> audit_logs, RefreshToken -> refresh_tokens
        """
        name = re.sub(r'(?<!^)(?=[A-Z])', '_', cls.__name__).lower()
        if name.endswith('y'):
            return name[:-1] + 'ies'
        return name + 's'
