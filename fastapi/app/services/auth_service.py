from datetime import datetime, timedelta, timezone
from typing import Optional
from jose import jwt, JWTError
from pydantic import ValidationError
from sqlalchemy.ext.asyncio import AsyncSession
from app.core.config import settings
from app.repositories.user_repository import UserRepository
from app.repositories.token_repository import TokenRepository
from app.core.security import verify_password, create_access_token, create_refresh_token, hash_token, create_action_token, decode_action_token, get_password_hash
from app.schemas.auth import LoginRequest, PasswordResetConfirm
from app.schemas.token import Token, TokenPayload
from app.models.user import RefreshToken
from app.services.captcha_service import CaptchaService
from app.core.exceptions import UnauthorizedException, BadRequestException, NotFoundException, ForbiddenException
from app.db.redis import get_redis

class AuthService:
    def __init__(self, db: AsyncSession):
        self.user_repo = UserRepository(db)
        self.token_repo = TokenRepository(db)

    async def authenticate(self, login_data: LoginRequest, ip_address: str = None, user_agent: str = None) -> Token:
        redis = await get_redis()
        lockout_key = f"lockout:{login_data.username}"
        attempts_key = f"attempts:{login_data.username}"
        
        # 1. VERIFY CAPTCHA FIRST
        is_captcha_valid = await CaptchaService.verify_captcha(
            login_data.captcha_id, 
            login_data.captcha_code
        )
        if not is_captcha_valid:
            raise BadRequestException(message="Invalid or expired captcha code")

        # 2. CHECK ACCOUNT LOCKOUT
        is_locked = await redis.get(lockout_key)
        if is_locked:
            ttl = await redis.ttl(lockout_key)
            time_str = f"{round(ttl/60, 1)} minutes" if ttl >= 60 else f"{ttl} seconds"
            raise ForbiddenException(
                message=f"Account is temporarily locked due to too many failed attempts. Please try again in {time_str}."
            )

        # 3. Check user
        user = await self.user_repo.get_by_username(login_data.username)
        
        # Handle password verification and lockout tracking
        if not user or not verify_password(login_data.password, user.hashed_password):
            # Increment failed attempts
            attempts = await redis.incr(attempts_key)
            if attempts == 1:
                await redis.expire(attempts_key, 3600) # Reset attempt counter after 1 hour of no activity
            
            if attempts >= 5:
                # Lock account for 15 minutes
                await redis.set(lockout_key, "true", ex=900)
                await redis.delete(attempts_key)
                raise ForbiddenException(
                    message="Too many failed login attempts. Your account has been locked for 15 minutes."
                )
                
            raise UnauthorizedException(message=f"Incorrect username or password. {5 - attempts} attempts remaining.")
        
        if not user.is_active:
            raise BadRequestException(message="Inactive user")

        # 4. Successful login - Reset attempts
        await redis.delete(attempts_key)

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
                raise UnauthorizedException(message="Invalid token type")
        except (JWTError, ValidationError):
            raise UnauthorizedException(message="Could not validate credentials")
        
        # 2. Check if token exists and is active in database (USING HASH)
        db_token = await self.token_repo.get_by_token(hash_token(refresh_token))
        if not db_token:
            raise UnauthorizedException(message="Refresh token expired or revoked")
            
        # 3. VERIFY DEVICE BINDING
        # For mobile apps, we prioritize device_id over User-Agent if provided
        if db_token.device_id and db_token.user_agent != user_agent:
             # If it's a known mobile device, we expect the same UA or at least same platform
             # For now, keeping the strict UA check as a baseline
             pass

        if db_token.user_agent != user_agent:
             await self.token_repo.revoke_token(db_token)
             raise UnauthorizedException(message="Session binding mismatch. Please login again.")

        # 4. Check user
        user = await self.user_repo.get_by_id(token_data.sub)
        if not user:
            raise NotFoundException(message="User not found")
        if not user.is_active:
            raise BadRequestException(message="Inactive user")

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

    async def blacklist_access_token(self, token: str) -> None:
        """
        Adds the given access token to the Redis blacklist with a TTL 
        matching the token's remaining validity period.
        """
        try:
            payload = jwt.decode(
                token, settings.JWT_SECRET, algorithms=[settings.ALGORITHM]
            )
            exp = payload.get("exp")
            if exp:
                now = int(datetime.now(timezone.utc).timestamp())
                ttl = exp - now
                if ttl > 0:
                    redis = await get_redis()
                    await redis.set(f"bl:{token}", "true", ex=ttl)
        except JWTError:
            # Token is invalid or expired anyway
            pass

    # --- Auth Lifecycle Methods ---

    async def request_password_reset(self, email: str) -> str:
        """
        Generates a password reset token. In production, this would be emailed.
        """
        user = await self.user_repo.get_by_email(email)
        if not user:
            # We return a success message anyway to prevent email enumeration attacks
            return "If the email exists, a reset link has been sent."
            
        token = create_action_token(subject=user.id, purpose="password-reset")
        # In a real app, send email here. For now, we return it for the user.
        return token

    async def reset_password(self, reset_data: PasswordResetConfirm) -> None:
        """
        Verifies the token and updates the password.
        """
        user_id = decode_action_token(reset_data.token, purpose="password-reset")
        if not user_id:
            raise BadRequestException(message="Invalid or expired reset token")
            
        user = await self.user_repo.get_by_id(user_id)
        if not user:
             raise NotFoundException(message="User not found")
             
        # Update password
        user.hashed_password = get_password_hash(reset_data.new_password)
        # Revoke all existing sessions for safety after password change
        await self.token_repo.revoke_all_user_tokens(user.id)
        await self.user_repo.update(user)

    async def request_email_verification(self, user_id: str) -> str:
        """
        Generates an email verification token.
        """
        token = create_action_token(subject=user_id, purpose="email-verification", expires_minutes=1440) # 24 hours
        return token

    async def verify_email(self, token: str) -> None:
        """
        Verifies the email token and marks user as verified.
        """
        user_id = decode_action_token(token, purpose="email-verification")
        if not user_id:
            raise BadRequestException(message="Invalid or expired verification token")
            
        user = await self.user_repo.get_by_id(user_id)
        if not user:
            raise NotFoundException(message="User not found")
            
        user.is_verified = True
        await self.user_repo.update(user)
