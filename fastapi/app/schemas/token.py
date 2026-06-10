from typing import Optional
from pydantic import BaseModel, EmailStr

class Token(BaseModel):
    access_token: Optional[str] = None
    refresh_token: Optional[str] = None
    token_type: Optional[str] = "bearer"
    mfa_required: bool = False
    mfa_token: Optional[str] = None
    password_expired: bool = False

class TokenPayload(BaseModel):
    sub: Optional[str] = None
    exp: Optional[int] = None
    type: Optional[str] = None
