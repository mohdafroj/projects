from typing import Any, Optional
from fastapi import APIRouter, Depends, status, Response, Cookie, Request, Header
from fastapi.security.utils import get_authorization_scheme_param
from fastapi.responses import Response as FastAPIResponse
from sqlalchemy.ext.asyncio import AsyncSession
from app.db.session import get_db
from app.schemas.auth import (
    LoginRequest, 
    RefreshTokenRequest, 
    PasswordResetRequest, 
    PasswordResetConfirm, 
    EmailVerificationRequest
)
from app.schemas.token import Token
from app.schemas.response import IResponse
from app.services.auth_service import AuthService
from app.api.dependencies.login import get_login_data
from app.api.dependencies.auth import check_csrf
from app.core.config import settings
from app.core.security import generate_csrf_token
from app.services.captcha_service import CaptchaService
from app.utils.rate_limiter import RateLimiter
from app.core.exceptions import UnauthorizedException, ForbiddenException

router = APIRouter()

@router.get("/captcha", tags=["Auth"], dependencies=[Depends(RateLimiter(times=20, seconds=60))])
async def get_captcha():
    """
    Generate a new captcha.
    """
    captcha_id, image_data = await CaptchaService.generate_captcha()
    return FastAPIResponse(
        content=image_data, 
        media_type="image/png",
        headers={"X-Captcha-ID": captcha_id}
    )

@router.post("/login", response_model=Token, dependencies=[Depends(RateLimiter(times=5, seconds=60))])
async def login(
    request: Request,
    response: Response,
    db: AsyncSession = Depends(get_db),
    login_data: LoginRequest = Depends(get_login_data)
) -> Any:
    """
    Unified login. Sets Refresh Token and CSRF cookies.
    """
    auth_service = AuthService(db)
    
    ip_address = request.client.host
    user_agent = request.headers.get("user-agent")
    
    token = await auth_service.authenticate(
        login_data, 
        ip_address=ip_address, 
        user_agent=user_agent
    )
    
    csrf_token = generate_csrf_token()
    
    # Cookie storage
    response.set_cookie(
        key="refresh_token",
        value=token.refresh_token,
        httponly=True,
        max_age=settings.REFRESH_TOKEN_EXPIRE_DAYS * 86400,
        samesite="lax",
        secure=not settings.DEBUG
    )
    
    response.set_cookie(
        key="csrf_token",
        value=csrf_token,
        httponly=False,
        max_age=settings.REFRESH_TOKEN_EXPIRE_DAYS * 86400,
        samesite="lax",
        secure=not settings.DEBUG
    )
    
    return token

@router.post("/refresh", response_model=Token, dependencies=[Depends(RateLimiter(times=10, seconds=60))])
async def refresh_token(
    request: Request,
    response: Response,
    refresh_data: Optional[RefreshTokenRequest] = None,
    refresh_token_cookie: Optional[str] = Cookie(None, alias="refresh_token"),
    x_csrf_token: Optional[str] = Header(None, alias="X-CSRF-Token"),
    csrf_token_cookie: Optional[str] = Cookie(None, alias="csrf_token"),
    db: AsyncSession = Depends(get_db)
) -> Any:
    """
    Refresh access token.
    """
    token_to_use = None
    if refresh_token_cookie:
        if not x_csrf_token or not csrf_token_cookie or x_csrf_token != csrf_token_cookie:
            raise ForbiddenException(message="CSRF token validation failed")
        token_to_use = refresh_token_cookie
    elif refresh_data and refresh_data.refresh_token:
        token_to_use = refresh_data.refresh_token
        
    if not token_to_use:
        raise UnauthorizedException(message="Refresh token missing in both cookie and body")
        
    auth_service = AuthService(db)
    ip_address = request.client.host
    user_agent = request.headers.get("user-agent")
    
    token = await auth_service.refresh_token(
        token_to_use,
        ip_address=ip_address,
        user_agent=user_agent
    )
    
    if refresh_token_cookie:
        response.set_cookie(
            key="refresh_token",
            value=token.refresh_token,
            httponly=True,
            max_age=settings.REFRESH_TOKEN_EXPIRE_DAYS * 86400,
            samesite="lax",
            secure=not settings.DEBUG
        )
    
    return token

@router.post("/logout", response_model=IResponse, dependencies=[Depends(check_csrf)])
async def logout(
    request: Request,
    response: Response,
    refresh_token_cookie: Optional[str] = Cookie(None, alias="refresh_token"),
    x_csrf_token: Optional[str] = Header(None, alias="X-CSRF-Token"),
    csrf_token_cookie: Optional[str] = Cookie(None, alias="csrf_token"),
    db: AsyncSession = Depends(get_db)
) -> Any:
    """
    Logout user. Revokes refresh token and blacklists the current access token.
    """
    auth_service = AuthService(db)
    
    # 1. Blacklist the current access token
    auth_header = request.headers.get("Authorization")
    if auth_header:
        scheme, access_token = get_authorization_scheme_param(auth_header)
        if scheme.lower() == "bearer":
            await auth_service.blacklist_access_token(access_token)
    
    # 2. Revoke the refresh token
    token_to_revoke = refresh_token_cookie
    if token_to_revoke:
        if not x_csrf_token or not csrf_token_cookie or x_csrf_token != csrf_token_cookie:
             raise ForbiddenException(message="CSRF token validation failed")
            
        from app.repositories.token_repository import TokenRepository
        from app.core.security import hash_token
        token_repo = TokenRepository(db)
        db_token = await token_repo.get_by_token(hash_token(token_to_revoke))
        if db_token:
            await token_repo.revoke_token(db_token)
    
    response.delete_cookie(key="refresh_token")
    response.delete_cookie(key="csrf_token")
    return IResponse(success=True, message="Logout successful")

# --- Auth Lifecycle Endpoints ---

@router.post("/password-reset-request", response_model=IResponse)
async def request_password_reset(
    data: PasswordResetRequest,
    db: AsyncSession = Depends(get_db)
) -> Any:
    """
    Request a password reset link.
    """
    auth_service = AuthService(db)
    result = await auth_service.request_password_reset(data.email)
    return IResponse(
        success=True,
        message="If the email exists, a reset link has been sent.",
        data={"token": result} if settings.DEBUG else None # Only show token in debug mode
    )

@router.post("/password-reset-confirm", response_model=IResponse)
async def reset_password(
    data: PasswordResetConfirm,
    db: AsyncSession = Depends(get_db)
) -> Any:
    """
    Confirm password reset using the token.
    """
    auth_service = AuthService(db)
    await auth_service.reset_password(data)
    return IResponse(success=True, message="Password has been reset successfully")

@router.post("/verify-email", response_model=IResponse)
async def verify_email(
    data: EmailVerificationRequest,
    db: AsyncSession = Depends(get_db)
) -> Any:
    """
    Verify user email using the token.
    """
    auth_service = AuthService(db)
    await auth_service.verify_email(data.token)
    return IResponse(success=True, message="Email verified successfully")
