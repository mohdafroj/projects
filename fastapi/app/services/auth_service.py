from datetime import datetime, timedelta, timezone
from typing import Optional
from fastapi import HTTPException, status
from jose import jwt, JWTError
from pydantic import ValidationError
from sqlalchemy.ext.asyncio import AsyncSession
from app.core.config import settings
from app.repositories.user_repository import UserRepository
from app.repositories.token_repository import TokenRepository
from app.core.security import verify_password, create_access_token, create_refresh_token, hash_token
from app.schemas.auth import LoginRequest
from app.schemas.token import Token, TokenPayload
from app.models.user import RefreshToken
from app.services.captcha_service import CaptchaService

class AuthService:
    def __init__(self, db: AsyncSession):
        self.user_repo = UserRepository(db)
        self.token_repo = TokenRepository(db)

    async def authenticate(self, login_data: LoginRequest, ip_address: str = None, user_agent: str = None) -> Token:
        # 1. VERIFY CAPTCHA FIRST
        is_captcha_valid = await CaptchaService.verify_captcha(
            login_data.captcha_id, 
            login_data.captcha_code
        )
        if not is_captcha_valid:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="Invalid or expired captcha code"
            )

        # 2. Check user
        user = await self.user_repo.get_by_username(login_data.username)
        if not user or not verify_password(login_data.password, user.hashed_password):
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Incorrect username or password",
                headers={"WWW-Authenticate": "Bearer"},
            )
        
        if not user.is_active:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="Inactive user"
            )

        access_token = create_access_token(subject=user.id)
        refresh_token_str = create_refresh_token(subject=user.id)
        
        # Store HASHED refresh token in database with device info
        db_token = RefreshToken(
            token=hash_token(refresh_token_str),
            user_id=user.id,
            expires_at=datetime.now(timezone.utc) + timedelta(days=settings.REFRESH_TOKEN_EXPIRE_DAYS),
            ip_address=ip_address,
            user_agent=user_agent,
            device_id=login_data.device_id,
            device_name=login_data.device_name,
            platform=login_data.platform
        )
        await self.token_repo.create(db_token)
        
        return Token(
            access_token=access_token,
            refresh_token=refresh_token_str
        )

    async def refresh_token(self, refresh_token: str, ip_address: str = None, user_agent: str = None) -> Token:
        # 1. Validate JWT structure and expiry
        try:
            payload = jwt.decode(
                refresh_token, settings.JWT_SECRET, algorithms=[settings.ALGORITHM]
            )
            token_data = TokenPayload(**payload)
            if token_data.type != "refresh":
                raise HTTPException(
                    status_code=status.HTTP_401_UNAUTHORIZED,
                    detail="Invalid token type",
                )
        except (JWTError, ValidationError):
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Could not validate credentials",
            )
        
        # 2. Check if token exists and is active in database (USING HASH)
        db_token = await self.token_repo.get_by_token(hash_token(refresh_token))
        if not db_token:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Refresh token expired or revoked"
            )
            
        # 3. VERIFY DEVICE BINDING
        if db_token.user_agent != user_agent:
             await self.token_repo.revoke_token(db_token)
             raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Session binding mismatch. Please login again."
            )

        # 4. Check user
        user = await self.user_repo.get_by_id(token_data.sub)
        if not user:
            raise HTTPException(status_code=404, detail="User not found")
        if not user.is_active:
            raise HTTPException(status_code=400, detail="Inactive user")

        # 5. Token rotation
        # Revoke old token
        await self.token_repo.revoke_token(db_token)
        
        # Create new tokens
        access_token = create_access_token(subject=user.id)
        new_refresh_token_str = create_refresh_token(subject=user.id)
        
        # Store new HASHED refresh token carrying over metadata
        new_db_token = RefreshToken(
            token=hash_token(new_refresh_token_str),
            user_id=user.id,
            expires_at=datetime.now(timezone.utc) + timedelta(days=settings.REFRESH_TOKEN_EXPIRE_DAYS),
            ip_address=ip_address,
            user_agent=user_agent,
            device_id=db_token.device_id,
            device_name=db_token.device_name,
            platform=db_token.platform
        )
        await self.token_repo.create(new_db_token)
        
        return Token(
            access_token=access_token,
            refresh_token=new_refresh_token_str
        )
