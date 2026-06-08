from typing import Any, List
from uuid import UUID
from fastapi import APIRouter, Depends, status, Query
from sqlalchemy.ext.asyncio import AsyncSession
from app.db.session import get_db
from app.schemas.rbac import (
    RoleCreate, RoleUpdate, RoleResponse, 
    PermissionCreate, PermissionUpdate, PermissionResponse, 
    AssignPermissionsRequest, AssignRolesRequest
)
from app.schemas.user import UserResponse
from app.schemas.response import IResponse
from app.services.rbac_service import RbacService
from app.api.dependencies.auth import require_permission
from app.models.user import User

router = APIRouter()

# --- Permissions ---

@router.post("/permissions", response_model=IResponse[PermissionResponse], status_code=status.HTTP_201_CREATED)
async def create_permission(
    data: PermissionCreate,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(require_permission("permission:manage"))
) -> Any:
    """Create a new permission."""
    rbac_service = RbacService(db)
    perm = await rbac_service.create_permission(data)
    return IResponse(success=True, message="Permission created", data=perm)

@router.get("/permissions", response_model=IResponse[List[PermissionResponse]])
async def list_permissions(
    skip: int = Query(0, ge=0),
    limit: int = Query(100, ge=1, le=500),
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(require_permission("permission:manage"))
) -> Any:
    """List all permissions."""
    rbac_service = RbacService(db)
    perms = await rbac_service.list_permissions(skip, limit)
    return IResponse(success=True, message="Permissions retrieved", data=perms)

@router.patch("/permissions/{perm_id}", response_model=IResponse[PermissionResponse])
async def update_permission(
    perm_id: UUID,
    data: PermissionUpdate,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(require_permission("permission:manage"))
) -> Any:
    """Update a permission."""
    rbac_service = RbacService(db)
    perm = await rbac_service.update_permission(str(perm_id), data)
    return IResponse(success=True, message="Permission updated", data=perm)

@router.delete("/permissions/{perm_id}", response_model=IResponse)
async def delete_permission(
    perm_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(require_permission("permission:manage"))
) -> Any:
    """Soft delete a permission."""
    rbac_service = RbacService(db)
    await rbac_service.delete_permission(str(perm_id))
    return IResponse(success=True, message="Permission deleted")


# --- Roles ---

@router.post("/roles", response_model=IResponse[RoleResponse], status_code=status.HTTP_201_CREATED)
async def create_role(
    data: RoleCreate,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(require_permission("role:manage"))
) -> Any:
    """Create a new role."""
    rbac_service = RbacService(db)
    role = await rbac_service.create_role(data)
    return IResponse(success=True, message="Role created", data=role)

@router.get("/roles", response_model=IResponse[List[RoleResponse]])
async def list_roles(
    skip: int = Query(0, ge=0),
    limit: int = Query(100, ge=1, le=500),
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(require_permission("role:manage"))
) -> Any:
    """List all roles with their assigned permissions."""
    rbac_service = RbacService(db)
    roles = await rbac_service.list_roles(skip, limit)
    return IResponse(success=True, message="Roles retrieved", data=roles)

@router.patch("/roles/{role_id}", response_model=IResponse[RoleResponse])
async def update_role(
    role_id: UUID,
    data: RoleUpdate,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(require_permission("role:manage"))
) -> Any:
    """Update a role's basic details."""
    rbac_service = RbacService(db)
    role = await rbac_service.update_role(str(role_id), data)
    return IResponse(success=True, message="Role updated", data=role)

@router.delete("/roles/{role_id}", response_model=IResponse)
async def delete_role(
    role_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(require_permission("role:manage"))
) -> Any:
    """Soft delete a role."""
    rbac_service = RbacService(db)
    await rbac_service.delete_role(str(role_id))
    return IResponse(success=True, message="Role deleted")


# --- Assignments ---

@router.post("/roles/{role_id}/permissions", response_model=IResponse[RoleResponse])
async def assign_permissions_to_role(
    role_id: UUID,
    data: AssignPermissionsRequest,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(require_permission("role:manage"))
) -> Any:
    """
    Assign permissions to a role. 
    This replaces all existing permissions for the role with the provided list.
    """
    rbac_service = RbacService(db)
    role = await rbac_service.assign_permissions_to_role(str(role_id), data)
    return IResponse(success=True, message="Permissions assigned to role", data=role)

@router.post("/users/{user_id}/roles", response_model=IResponse[UserResponse])
async def assign_roles_to_user(
    user_id: UUID,
    data: AssignRolesRequest,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(require_permission("role:manage"))
) -> Any:
    """
    Assign roles to a user.
    This replaces all existing roles for the user with the provided list.
    """
    rbac_service = RbacService(db)
    user = await rbac_service.assign_roles_to_user(str(user_id), data)
    return IResponse(success=True, message="Roles assigned to user", data=user)
