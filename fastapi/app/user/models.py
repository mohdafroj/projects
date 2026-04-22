from sqlalchemy import Column, Integer, String, Text, Boolean, datetime
from app.db.session import Base

class User(Base):
    __tablename__ = "users"
    
    id = Column(Integer, primary_key=True, index=True)
    name = Column(String)
    email = Column(String, unique=True, index=True)
    username = Column(String, unique=True, index=True)
    hashed_password = Column(Text)
    status = Column(Boolean, default=True)  # 1 for active, 0 for inactive
    created_at = Column(datetime, default=datetime.utcnow)
    updated_at = Column(datetime, default=datetime.utcnow, onupdate=datetime.utcnow)