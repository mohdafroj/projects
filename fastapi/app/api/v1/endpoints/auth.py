from typing import Any, Optional
from fastapi import APIRouter, Depends, status, Response, Cookie, Request, Header
from fastapi.responses import Response as FastAPIResponse
from sqlalchemy.ext.asyncio import AsyncSession
from app.db.session import get_db
from app.schemas.auth import LoginRequest, RefreshTokenRequest
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
    Supports both HTTP-only cookie (secure) and JSON body (fallback for mobile/API clients).
    """
    # 1. Determine which token to use
    token_to_use = None
    if refresh_token_cookie:
        # Browser flow: Validate CSRF
        if not x_csrf_token or not csrf_token_cookie or x_csrf_token != csrf_token_cookie:
            raise ForbiddenException(message="CSRF token validation failed")
        token_to_use = refresh_token_cookie
    elif refresh_data and refresh_data.refresh_token:
        # API/Mobile flow: Use body
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
    
    # Update cookies if they were used
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

@router.post("/logout", response_model=IResponse)
async def logout(
    request: Request,
    response: Response,
    refresh_token_cookie: Optional[str] = Cookie(None, alias="refresh_token"),
    x_csrf_token: Optional[str] = Header(None, alias="X-CSRF-Token"),
    csrf_token_cookie: Optional[str] = Cookie(None, alias="csrf_token"),
    db: AsyncSession = Depends(get_db)
) -> Any:
    """
    Logout user.
    """
    # 1. Identify token
    token_to_revoke = refresh_token_cookie
    
    if token_to_revoke:
        # Browser flow: Check CSRF
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
