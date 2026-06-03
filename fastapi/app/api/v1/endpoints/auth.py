from typing import Any, Optional
from fastapi import APIRouter, Depends, status, Response, Cookie, HTTPException, Request
from fastapi.responses import Response as FastAPIResponse
from sqlalchemy.ext.asyncio import AsyncSession
from app.db.session import get_db
from app.schemas.auth import LoginRequest, RefreshTokenRequest
from app.schemas.token import Token
from app.schemas.response import IResponse
from app.services.auth_service import AuthService
from app.api.dependencies.login import get_login_data
from app.core.config import settings
from app.services.captcha_service import CaptchaService

router = APIRouter()

@router.get("/captcha", tags=["Auth"])
async def get_captcha():
    """
    Generate a new captcha.
    Returns the captcha_id in headers and the image as the body.
    """
    captcha_id, image_data = await CaptchaService.generate_captcha()
    return FastAPIResponse(
        content=image_data, 
        media_type="image/png",
        headers={"X-Captcha-ID": captcha_id}
    )

@router.post("/login", response_model=Token)
async def login(
    request: Request,
    response: Response,
    db: AsyncSession = Depends(get_db),
    login_data: LoginRequest = Depends(get_login_data)
) -> Any:
    """
    OAuth2 compatible token login. 
    Requires captcha verification.
    Binds the session to the client's IP and User-Agent.
    """
    auth_service = AuthService(db)
    
    # Capture metadata
    ip_address = request.client.host
    user_agent = request.headers.get("user-agent")
    
    token = await auth_service.authenticate(
        login_data, 
        ip_address=ip_address, 
        user_agent=user_agent
    )
    
    # Set Refresh Token in HTTP-only Cookie
    response.set_cookie(
        key="refresh_token",
        value=token.refresh_token,
        httponly=True,
        max_age=settings.REFRESH_TOKEN_EXPIRE_DAYS * 86400,
        samesite="lax",
        secure=not settings.DEBUG
    )
    
    return token

@router.post("/refresh", response_model=Token)
async def refresh_token(
    request: Request,
    response: Response,
    refresh_token_cookie: Optional[str] = Cookie(None, alias="refresh_token"),
    db: AsyncSession = Depends(get_db)
) -> Any:
    """
    Refresh access token using the refresh token stored in the HTTP-only cookie.
    Verifies that the request comes from the same device/session.
    """
    if not refresh_token_cookie:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Refresh token missing"
        )
        
    auth_service = AuthService(db)
    
    # Capture current metadata for verification
    ip_address = request.client.host
    user_agent = request.headers.get("user-agent")
    
    token = await auth_service.refresh_token(
        refresh_token_cookie,
        ip_address=ip_address,
        user_agent=user_agent
    )
    
    # Update Refresh Token in HTTP-only Cookie (Rotation)
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
    response: Response,
    refresh_token_cookie: Optional[str] = Cookie(None, alias="refresh_token"),
    db: AsyncSession = Depends(get_db)
) -> Any:
    """
    Logout user by revoking the refresh token in the database and clearing the cookie.
    """
    if refresh_token_cookie:
        from app.repositories.token_repository import TokenRepository
        from app.core.security import hash_token
        token_repo = TokenRepository(db)
        db_token = await token_repo.get_by_token(hash_token(refresh_token_cookie))
        if db_token:
            await token_repo.revoke_token(db_token)
    
    response.delete_cookie(key="refresh_token")
    return IResponse(success=True, message="Logout successful")
