from typing import Any
from fastapi import APIRouter, Depends, status
from sqlalchemy.ext.asyncio import AsyncSession
from app.db.session import get_db
from app.schemas.auth import LoginRequest, RefreshTokenRequest
from app.schemas.token import Token
from app.schemas.response import IResponse
from app.services.auth_service import AuthService
from app.api.dependencies.login import get_login_data

router = APIRouter()

@router.post("/login", response_model=Token)
async def login(
    db: AsyncSession = Depends(get_db),
    login_data: LoginRequest = Depends(get_login_data)
) -> Any:
    """
    OAuth2 compatible token login. 
    Returns a flat Token object for Swagger UI compatibility.
    """
    auth_service = AuthService(db)
    token = await auth_service.authenticate(login_data)
    return token

@router.post("/refresh", response_model=Token)
async def refresh_token(
    refresh_data: RefreshTokenRequest,
    db: AsyncSession = Depends(get_db)
) -> Any:
    """
    Refresh access token using a refresh token.
    Returns a flat Token object for Swagger UI compatibility.
    """
    auth_service = AuthService(db)
    token = await auth_service.refresh_token(refresh_data.refresh_token)
    return token
