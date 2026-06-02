from typing import Any, List
from uuid import UUID
from fastapi import APIRouter, Depends, status, HTTPException, Query
from sqlalchemy.ext.asyncio import AsyncSession
from app.db.session import get_db
from app.schemas.user import UserCreate, UserResponse, UserUpdate, UserCreateAdmin
from app.schemas.response import IResponse
from app.services.user_service import UserService
from app.api.dependencies.auth import get_current_user, get_current_active_superuser
from app.models.user import User
from app.repositories.user_repository import UserRepository

router = APIRouter()

@router.post("/register", response_model=IResponse[UserResponse], status_code=status.HTTP_201_CREATED)
async def register_user(
    user_data: UserCreate,
    db: AsyncSession = Depends(get_db)
) -> Any:
    """
    Register a new user and assign the default "User" role.
    """
    user_service = UserService(db)
    user = await user_service.register_user(user_data)
    return IResponse(
        success=True,
        message="User registered successfully",
        data=user
    )

@router.post("/", response_model=IResponse[UserResponse], status_code=status.HTTP_201_CREATED)
async def create_user(
    user_data: UserCreateAdmin,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_active_superuser)
) -> Any:
    """
    Create a new user with admin privileges (Admin only).
    """
    user_service = UserService(db)
    user = await user_service.create_user_admin(user_data)
    return IResponse(
        success=True,
        message="User created successfully",
        data=user
    )

@router.patch("/me", response_model=IResponse[UserResponse])
async def update_user_me(
    update_data: UserUpdate,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user)
) -> Any:
    """
    Update own user information.
    """
    user_service = UserService(db)
    user = await user_service.update_user(current_user, update_data)
    return IResponse(
        success=True,
        message="Profile updated successfully",
        data=user
    )

@router.patch("/{user_id}", response_model=IResponse[UserResponse])
async def update_user(
    user_id: UUID,
    update_data: UserUpdate,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_active_superuser)
) -> Any:
    """
    Update a user (Admin only).
    """
    user_repo = UserRepository(db)
    user = await user_repo.get_by_id(user_id)
    if not user:
        raise HTTPException(status_code=404, detail="User not found")
    
    user_service = UserService(db)
    user = await user_service.update_user(user, update_data)
    return IResponse(
        success=True,
        message="User updated successfully",
        data=user
    )

@router.get("/", response_model=IResponse[List[UserResponse]])
async def list_users(
    db: AsyncSession = Depends(get_db),
    skip: int = Query(0, ge=0),
    limit: int = Query(10, ge=1, le=100),
    current_user: User = Depends(get_current_active_superuser)
) -> Any:
    """
    List all users with pagination (Admin only).
    """
    user_service = UserService(db)
    users = await user_service.list_users(skip=skip, limit=limit)
    return IResponse(
        success=True,
        message="Users retrieved successfully",
        data=users
    )

@router.delete("/{user_id}", response_model=IResponse[UserResponse])
async def delete_user(
    user_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_active_superuser)
) -> Any:
    """
    Soft delete a user (Admin only).
    """
    user_repo = UserRepository(db)
    user = await user_repo.get_by_id(user_id)
    if not user:
        raise HTTPException(status_code=404, detail="User not found")
    
    if user.is_super_admin:
        raise HTTPException(
            status_code=400, detail="Super admin users cannot be deleted"
        )
    
    user_service = UserService(db)
    user = await user_service.delete_user(user)
    return IResponse(
        success=True,
        message="User soft-deleted successfully",
        data=user
    )

@router.delete("/{user_id}/hard", status_code=status.HTTP_204_NO_CONTENT)
async def permanent_delete_user(
    user_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_active_superuser)
) -> None:
    """
    Permanently delete a user from the database (Admin only).
    """
    if user_id == current_user.id:
        raise HTTPException(
            status_code=400, detail="Super admin users cannot delete themselves"
        )
        
    user_repo = UserRepository(db)
    user = await user_repo.get_by_id_any(user_id)
    if not user:
        raise HTTPException(status_code=404, detail="User not found")
    
    user_service = UserService(db)
    await user_service.permanent_delete_user(user)
    return None
