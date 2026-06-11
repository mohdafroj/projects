from sqlalchemy import Column, String, Integer, ForeignKey, JSON, Text
from sqlalchemy.dialects.postgresql import UUID
from app.db.base import Base

class AuditLog(Base):
    """
    Model for storing system-wide audit trails.
    """
    user_id = Column(UUID(as_uuid=True), ForeignKey("users.id", ondelete="SET NULL"), nullable=True)
    action = Column(String(50), nullable=False)    # e.g., POST, PATCH, DELETE
    resource = Column(String(255), nullable=False) # e.g., /api/v1/users/
    status_code = Column(Integer, nullable=False)
    request_id = Column(String(50), index=True, nullable=True) # Correlation ID
    ip_address = Column(String(45), nullable=True)
    user_agent = Column(String(512), nullable=True)
    metadata_json = Column(JSON, nullable=True)     # Stores request info, IDs, etc.
    description = Column(Text, nullable=True)
