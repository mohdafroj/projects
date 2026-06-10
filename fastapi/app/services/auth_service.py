from datetime import datetime, timedelta, timezone
from typing import Optional
from jose import jwt, JWTError
from pydantic import ValidationError
from sqlalchemy.ext.asyncio import AsyncSession
from app.core.config import settings
from app.repositories.user_repository import UserRepository
from app.repositories.token_repository import TokenRepository
from app.repositories.password_history_repository import PasswordHistoryRepository
from app.core.security import verify_password, create_access_token, create_refresh_token, hash_token, create_action_token, decode_action_token, get_password_hash, decode_nested_token
from app.schemas.auth import LoginRequest, PasswordResetConfirm
from app.schemas.token import Token, TokenPayload
from app.models.user import RefreshToken
from app.services.captcha_service import CaptchaService
from app.core.exceptions import UnauthorizedException, BadRequestException, NotFoundException, ForbiddenException
from app.db.redis import get_redis
import pyotp

class AuthService:
    def __init__(self, db: AsyncSession):
        self.user_repo = UserRepository(db)
        self.token_repo = TokenRepository(db)
        self.history_repo = PasswordHistoryRepository(db)

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
            attempts = await redis.incr(attempts_key)
            if attempts == 1:
                await redis.expire(attempts_key, 3600)
            
            if attempts >= 5:
                await redis.set(lockout_key, "true", ex=900)
                await redis.delete(attempts_key)
                raise ForbiddenException(
                    message="Too many failed login attempts. Your account has been locked for 15 minutes."
                )
            raise UnauthorizedException(message=f"Incorrect username or password. {5 - attempts} attempts remaining.")
        
        if not user.is_active:
            raise BadRequestException(message="Inactive user")

        # 4. Successful login credentials - Reset attempts
        await redis.delete(attempts_key)

        # Check password expiration (90 days)
        password_expired = False
        if user.password_changed_at and (datetime.now(timezone.utc) - user.password_changed_at).days > 90:
            password_expired = True

        # 5. Check if MFA is enabled
        if user.is_mfa_enabled and user.totp_secret:
            mfa_token_str = create_action_token(subject=user.id, purpose="mfa-login", expires_minutes=5)
            return Token(
                mfa_required=True,
                mfa_token=mfa_token_str,
                password_expired=password_expired
            )

        # 6. Standard Login Flow (No MFA)
        return await self._issue_tokens_and_save(user, ip_address, user_agent, login_data.device_id, login_data.device_name, login_data.platform, password_expired)

    async def _issue_tokens_and_save(self, user, ip_address, user_agent, device_id, device_name, platform, password_expired=False) -> Token:
        access_token = create_access_token(subject=user.id)
        refresh_token_str = create_refresh_token(subject=user.id)
        
        db_token = RefreshToken(
            token=hash_token(refresh_token_str),
            user_id=user.id,
            expires_at=datetime.now(timezone.utc) + timedelta(days=settings.REFRESH_TOKEN_EXPIRE_DAYS),
            ip_address=ip_address,
            user_agent=user_agent,
            device_id=device_id,
            device_name=device_name,
            platform=platform
        )
        await self.token_repo.create(db_token)
        
        return Token(
            access_token=access_token,
            refresh_token=refresh_token_str,
            password_expired=password_expired
        )

    # --- MFA Methods ---

    async def verify_mfa_login(self, mfa_token: str, code: str, ip_address: str = None, user_agent: str = None, device_id: str = None, device_name: str = None, platform: str = None) -> Token:
        """Verifies the TOTP code and issues final session tokens."""
        user_id = decode_action_token(mfa_token, purpose="mfa-login")
        if not user_id:
            raise UnauthorizedException(message="Invalid or expired MFA token")
            
        user = await self.user_repo.get_by_id(user_id)
        if not user or not user.is_active or not user.is_mfa_enabled:
            raise UnauthorizedException(message="Invalid MFA state")

        totp = pyotp.TOTP(user.totp_secret)
        if not totp.verify(code):
            raise UnauthorizedException(message="Invalid authenticator code")

        password_expired = False
        if user.password_changed_at and (datetime.now(timezone.utc) - user.password_changed_at).days > 90:
            password_expired = True

        return await self._issue_tokens_and_save(user, ip_address, user_agent, device_id, device_name, platform, password_expired)

    async def setup_mfa(self, user_id: str) -> dict:
        """Generates a new TOTP secret and QR code URI, but does not enable it yet."""
        user = await self.user_repo.get_by_id(user_id)
        if not user:
             raise NotFoundException(message="User not found")
             
        secret = pyotp.random_base32()
        totp = pyotp.TOTP(secret)
        qr_code_url = totp.provisioning_uri(name=user.email, issuer_name=settings.PROJECT_NAME)
        
        return {"secret": secret, "qr_code_url": qr_code_url}

    async def enable_mfa(self, user_id: str, secret: str, code: str) -> None:
        """Verifies the first code and permanently enables MFA for the user."""
        user = await self.user_repo.get_by_id(user_id)
        if not user:
             raise NotFoundException(message="User not found")
             
        totp = pyotp.TOTP(secret)
        if not totp.verify(code):
             raise BadRequestException(message="Invalid authenticator code")
             
        user.totp_secret = secret
        user.is_mfa_enabled = True
        await self.user_repo.update(user)

    async def refresh_token(self, refresh_token: str, ip_address: str = None, user_agent: str = None) -> Token:
        # 1. Validate JWT structure and expiry
        try:
            payload = decode_nested_token(refresh_token)
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
        if db_token.device_id and db_token.user_agent != user_agent:
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
        await self.token_repo.revoke_token(db_token)
        
        access_token = create_access_token(subject=user.id)
        new_refresh_token_str = create_refresh_token(subject=user.id)
        
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
            payload = decode_nested_token(token)
            exp = payload.get("exp")
            if exp:
                now = int(datetime.now(timezone.utc).timestamp())
                ttl = exp - now
                if ttl > 0:
                    redis = await get_redis()
                    await redis.set(f"bl:{token}", "true", ex=ttl)
        except JWTError:
            pass

    # --- Auth Lifecycle Methods ---

    async def request_password_reset(self, email: str) -> str:
        """
        Generates a password reset token. In production, this would be emailed.
        """
        user = await self.user_repo.get_by_email(email)
        if not user:
            return "If the email exists, a reset link has been sent."
            
        token = create_action_token(subject=user.id, purpose="password-reset")
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
             
        # Check Pwned Password
        from app.utils.pwned import check_pwned_password
        pwned_count = await check_pwned_password(reset_data.new_password)
        if pwned_count > 0:
            raise BadRequestException(message=f"Password has been exposed in {pwned_count} data breaches. Please choose a different one.")
            
        # Check Password History
        history = await self.history_repo.get_user_history(str(user.id), limit=5)
        for old_pw in history:
            if verify_password(reset_data.new_password, old_pw.hashed_password):
                raise BadRequestException(message="Cannot reuse any of your last 5 passwords.")
             
        # Update password
        hashed_pw = get_password_hash(reset_data.new_password)
        user.hashed_password = hashed_pw
        user.password_changed_at = datetime.now(timezone.utc)
        await self.history_repo.add_history(str(user.id), hashed_pw)
        
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

    # --- Session Management ---

    async def get_active_sessions(self, user_id: str) -> list[RefreshToken]:
        return await self.token_repo.get_active_by_user_id(user_id)

    async def revoke_session(self, user_id: str, session_id: str) -> None:
        session = await self.token_repo.get_by_id(session_id)
        if not session:
            raise NotFoundException(message="Session not found")
            
        if str(session.user_id) != str(user_id):
             raise ForbiddenException(message="You can only revoke your own sessions")
             
        await self.token_repo.revoke_token(session)
