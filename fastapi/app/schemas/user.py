from typing import Optional, List
from uuid import UUID
from pydantic import BaseModel, EmailStr, ConfigDict

class UserBase(BaseModel):
    email: EmailStr
    username: str
    full_name: Optional[str] = None
    is_active: Optional[bool] = True

class UserCreate(UserBase):
    password: str

class UserCreateAdmin(UserCreate):
    is_verified: Optional[bool] = False
    is_super_admin: Optional[bool] = False

class UserUpdate(BaseModel):
    email: Optional[EmailStr] = None
    username: Optional[str] = None
    full_name: Optional[str] = None
    password: Optional[str] = None
    is_active: Optional[bool] = None

class UserResponse(UserBase):
    id: UUID
    is_verified: bool
    is_super_admin: bool
    
    model_config = ConfigDict(from_attributes=True)
