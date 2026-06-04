from fastapi import HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession
from app.repositories.user_repository import UserRepository, RoleRepository
from app.models.user import User
from app.schemas.user import UserCreate, UserUpdate, UserCreateAdmin
from app.core.security import get_password_hash
from app.core.exceptions import BadRequestException, NotFoundException
from typing import List, Optional

class UserService:
    def __init__(self, db: AsyncSession):
        self.db = db
        self.user_repo = UserRepository(db)
        self.role_repo = RoleRepository(db)

    async def register_user(self, user_data: UserCreate) -> User:
        # Check if email exists
        if await self.user_repo.get_by_email(user_data.email):
            raise BadRequestException(message="A user with this email already exists")
        
        # Check if username exists
        if await self.user_repo.get_by_username(user_data.username):
            raise BadRequestException(message="A user with this username already exists")
        
        # Get default "User" role
        default_role = await self.role_repo.get_by_name("User")
        roles = [default_role] if default_role else []

        # Create user object
        new_user = User(
            email=user_data.email,
            username=user_data.username,
            full_name=user_data.full_name,
            hashed_password=get_password_hash(user_data.password),
            is_active=True,
            roles=roles
        )
        
        return await self.user_repo.create(new_user)

    async def create_user_admin(self, user_data: UserCreateAdmin) -> User:
        # Check if email exists
        if await self.user_repo.get_by_email(user_data.email):
            raise BadRequestException(message="A user with this email already exists")
        
        # Check if username exists
        if await self.user_repo.get_by_username(user_data.username):
            raise BadRequestException(message="A user with this username already exists")
        
        # Get default "User" role
        default_role = await self.role_repo.get_by_name("User")
        roles = [default_role] if default_role else []

        # Create user object with admin flags
        new_user = User(
            email=user_data.email,
            username=user_data.username,
            full_name=user_data.full_name,
            hashed_password=get_password_hash(user_data.password),
            is_active=user_data.is_active,
            is_verified=user_data.is_verified,
            is_super_admin=user_data.is_super_admin,
            roles=roles
        )
        
        return await self.user_repo.create(new_user)

    async def update_user(self, user: User, update_data: UserUpdate) -> User:
        data = update_data.model_dump(exclude_unset=True)
        
        if "password" in data:
            data["hashed_password"] = get_password_hash(data.pop("password"))
        
        for field, value in data.items():
            setattr(user, field, value)
        
        return await self.user_repo.update(user)

    async def list_users(self, skip: int = 0, limit: int = 100) -> List[User]:
        return await self.user_repo.list(skip=skip, limit=limit)

    async def delete_user(self, user: User) -> User:
        return await self.user_repo.soft_delete(user)

    async def permanent_delete_user(self, user: User) -> None:
        await self.user_repo.hard_delete(user)
