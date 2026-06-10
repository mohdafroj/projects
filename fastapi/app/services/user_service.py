from fastapi import HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession
from app.repositories.user_repository import UserRepository, RoleRepository
from app.repositories.password_history_repository import PasswordHistoryRepository
from app.models.user import User
from app.schemas.user import UserCreate, UserUpdate, UserCreateAdmin
from app.core.security import get_password_hash, verify_password
from app.core.exceptions import BadRequestException, NotFoundException
from app.utils.pwned import check_pwned_password
from typing import List, Optional
from datetime import datetime, timezone

class UserService:
    def __init__(self, db: AsyncSession):
        self.db = db
        self.user_repo = UserRepository(db)
        self.role_repo = RoleRepository(db)
        self.history_repo = PasswordHistoryRepository(db)

    async def register_user(self, user_data: UserCreate) -> User:
        # Check Pwned Password
        pwned_count = await check_pwned_password(user_data.password)
        if pwned_count > 0:
            raise BadRequestException(message=f"Password has been exposed in {pwned_count} data breaches. Please choose a different one.")
            
        # Check if email exists
        if await self.user_repo.get_by_email(user_data.email):
            raise BadRequestException(message="A user with this email already exists")
        
        # Check if username exists
        if await self.user_repo.get_by_username(user_data.username):
            raise BadRequestException(message="A user with this username already exists")
        
        # Get default "User" role
        default_role = await self.role_repo.get_by_name("User")
        roles = [default_role] if default_role else []

        hashed_pw = get_password_hash(user_data.password)
        # Create user object
        new_user = User(
            email=user_data.email,
            username=user_data.username,
            full_name=user_data.full_name,
            hashed_password=hashed_pw,
            is_active=True,
            roles=roles
        )
        
        user = await self.user_repo.create(new_user)
        await self.history_repo.add_history(str(user.id), hashed_pw)
        return user

    async def create_user_admin(self, user_data: UserCreateAdmin) -> User:
        # Check Pwned Password
        pwned_count = await check_pwned_password(user_data.password)
        if pwned_count > 0:
            raise BadRequestException(message=f"Password has been exposed in {pwned_count} data breaches. Please choose a different one.")
            
        # Check if email exists
        if await self.user_repo.get_by_email(user_data.email):
            raise BadRequestException(message="A user with this email already exists")
        
        # Check if username exists
        if await self.user_repo.get_by_username(user_data.username):
            raise BadRequestException(message="A user with this username already exists")
        
        # Get default "User" role
        default_role = await self.role_repo.get_by_name("User")
        roles = [default_role] if default_role else []

        hashed_pw = get_password_hash(user_data.password)
        # Create user object with admin flags
        new_user = User(
            email=user_data.email,
            username=user_data.username,
            full_name=user_data.full_name,
            hashed_password=hashed_pw,
            is_active=user_data.is_active,
            is_verified=user_data.is_verified,
            is_super_admin=user_data.is_super_admin,
            roles=roles
        )
        
        user = await self.user_repo.create(new_user)
        await self.history_repo.add_history(str(user.id), hashed_pw)
        return user

    async def update_user(self, user: User, update_data: UserUpdate) -> User:
        data = update_data.model_dump(exclude_unset=True)
        
        if "password" in data:
            new_password = data.pop("password")
            
            # Check Pwned Password
            pwned_count = await check_pwned_password(new_password)
            if pwned_count > 0:
                raise BadRequestException(message=f"Password has been exposed in {pwned_count} data breaches. Please choose a different one.")
            
            # Check Password History
            history = await self.history_repo.get_user_history(str(user.id), limit=5)
            for old_pw in history:
                if verify_password(new_password, old_pw.hashed_password):
                    raise BadRequestException(message="Cannot reuse any of your last 5 passwords.")
            
            # Update password
            hashed_pw = get_password_hash(new_password)
            data["hashed_password"] = hashed_pw
            data["password_changed_at"] = datetime.now(timezone.utc)
            await self.history_repo.add_history(str(user.id), hashed_pw)
        
        for field, value in data.items():
            setattr(user, field, value)
        
        return await self.user_repo.update(user)

    async def list_users(self, skip: int = 0, limit: int = 100) -> List[User]:
        return await self.user_repo.list(skip=skip, limit=limit)

    async def delete_user(self, user: User) -> User:
        return await self.user_repo.soft_delete(user)

    async def permanent_delete_user(self, user: User) -> None:
        await self.user_repo.hard_delete(user)
