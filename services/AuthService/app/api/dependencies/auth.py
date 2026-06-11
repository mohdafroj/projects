from typing import Callable, List, Union
from fastapi import Depends, Header, Cookie
from fastapi.security import OAuth2PasswordBearer
from jose import JWTError
from pydantic import ValidationError
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.orm import selectinload

from app.core.config import settings
from app.db.session import get_db
from app.models.user import User, Role, Permission
from app.repositories.user_repository import UserRepository
from app.schemas.token import TokenPayload
from app.core.exceptions import UnauthorizedException, ForbiddenException, NotFoundException, BadRequestException
from app.db.redis import get_redis
from app.core.security import decode_nested_token

reusable_oauth2 = OAuth2PasswordBearer(
    tokenUrl=f"{settings.API_V1_STR}/auth/login"
)

async def get_current_user(
    db: AsyncSession = Depends(get_db),
    token: str = Depends(reusable_oauth2)
) -> User:
    try:
        payload = decode_nested_token(token)
        token_data = TokenPayload(**payload)
        if token_data.type != "access":
            raise UnauthorizedException(message="Invalid token type")
            
        # Check if access token is blacklisted
        redis = await get_redis()
        is_blacklisted = await redis.get(f"bl:{token}")
        if is_blacklisted:
            raise UnauthorizedException(message="Token has been revoked")
            
    except (JWTError, ValidationError):
        raise ForbiddenException(message="Could not validate credentials")
    
    query = (
        select(User)
        .where(User.id == token_data.sub, User.is_deleted == False)
        .options(
            selectinload(User.roles).selectinload(Role.permissions)
        )
    )
    result = await db.execute(query)
    user = result.scalar_one_or_none()

    if not user:
        raise NotFoundException(message="User not found")
    if not user.is_active:
        raise BadRequestException(message="Inactive user")
    
    return user

async def get_current_active_superuser(
    current_user: User = Depends(get_current_user),
) -> User:
    if not current_user.is_super_admin:
        raise ForbiddenException(message="The user doesn't have enough privileges")
    return current_user

async def check_csrf(
    x_csrf_token: str = Header(None, alias="X-CSRF-Token"),
    csrf_token_cookie: str = Cookie(None, alias="csrf_token")
):
    """
    Verifies that the CSRF token in the header matches the one in the cookie.
    """
    if not x_csrf_token or not csrf_token_cookie or x_csrf_token != csrf_token_cookie:
        raise ForbiddenException(message="CSRF token validation failed")
    return True

def require_permission(permissions: Union[str, List[str]], any_of: bool = True) -> Callable:
    """
    Dependency factory for permission-based authorization.
    """
    if isinstance(permissions, str):
        permissions = [permissions]

    async def permission_dependency(
        current_user: User = Depends(get_current_user)
    ) -> User:
        if current_user.is_super_admin:
            return current_user

        user_permissions = set()
        for role in current_user.roles:
            if role.is_active:
                for perm in role.permissions:
                    user_permissions.add(perm.name)
        
        if any_of:
            has_permission = any(p in user_permissions for p in permissions)
        else:
            has_permission = all(p in user_permissions for p in permissions)
        
        if not has_permission:
            logic_str = "any of" if any_of else "all of"
            raise ForbiddenException(
                message=f"Missing required permission: {logic_str} {permissions}"
            )
        
        return current_user

    return permission_dependency
