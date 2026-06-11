from typing import Optional
from uuid import UUID
from datetime import datetime
from pydantic import BaseModel, EmailStr, ConfigDict

class SessionResponse(BaseModel):
    id: UUID
    ip_address: Optional[str] = None
    user_agent: Optional[str] = None
    device_id: Optional[str] = None
    device_name: Optional[str] = None
    platform: Optional[str] = None
    created_at: datetime
    expires_at: datetime
    
    model_config = ConfigDict(from_attributes=True)

class LoginRequest(BaseModel):
    username: str
    password: str
    captcha_id: str
    captcha_code: str
    device_id: Optional[str] = None
    device_name: Optional[str] = None
    platform: Optional[str] = None

class RefreshTokenRequest(BaseModel):
    refresh_token: str

class PasswordResetRequest(BaseModel):
    email: EmailStr

class PasswordResetConfirm(BaseModel):
    token: str
    new_password: str

class EmailVerificationRequest(BaseModel):
    token: str

class MFASetupResponse(BaseModel):
    secret: str
    qr_code_url: str

class MFAEnableRequest(BaseModel):
    secret: str
    code: str

class MFAVerifyRequest(BaseModel):
    mfa_token: str
    code: str
    device_id: Optional[str] = None
    device_name: Optional[str] = None
    platform: Optional[str] = None
