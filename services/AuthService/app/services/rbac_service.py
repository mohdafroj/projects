from typing import List
from sqlalchemy.ext.asyncio import AsyncSession
from app.repositories.rbac_repository import RoleRepository, PermissionRepository
from app.repositories.user_repository import UserRepository
from app.models.user import Role, Permission, User
from app.schemas.rbac import RoleCreate, RoleUpdate, PermissionCreate, PermissionUpdate, AssignPermissionsRequest, AssignRolesRequest
from app.core.exceptions import BadRequestException, NotFoundException

class RbacService:
    def __init__(self, db: AsyncSession):
        self.db = db
        self.role_repo = RoleRepository(db)
        self.perm_repo = PermissionRepository(db)
        self.user_repo = UserRepository(db)

    # --- Permissions ---

    async def create_permission(self, data: PermissionCreate) -> Permission:
        if await self.perm_repo.get_by_name(data.name):
            raise BadRequestException(f"Permission '{data.name}' already exists.")
        
        perm = Permission(**data.model_dump())
        return await self.perm_repo.create(perm)

    async def list_permissions(self, skip: int = 0, limit: int = 100) -> List[Permission]:
        return await self.perm_repo.list(skip=skip, limit=limit)

    async def update_permission(self, perm_id: str, data: PermissionUpdate) -> Permission:
        perm = await self.perm_repo.get_by_id(perm_id)
        if not perm:
            raise NotFoundException("Permission not found")
            
        update_data = data.model_dump(exclude_unset=True)
        if "name" in update_data and update_data["name"] != perm.name:
            if await self.perm_repo.get_by_name(update_data["name"]):
                raise BadRequestException(f"Permission '{update_data['name']}' already exists.")
                
        for field, value in update_data.items():
            setattr(perm, field, value)
            
        return await self.perm_repo.update(perm)

    async def delete_permission(self, perm_id: str) -> None:
        perm = await self.perm_repo.get_by_id(perm_id)
        if not perm:
            raise NotFoundException("Permission not found")
        await self.perm_repo.delete(perm)


    # --- Roles ---

    async def create_role(self, data: RoleCreate) -> Role:
        if await self.role_repo.get_by_name(data.name):
            raise BadRequestException(f"Role '{data.name}' already exists.")
        
        role = Role(**data.model_dump())
        role = await self.role_repo.create(role)
        return await self.role_repo.get_by_id(str(role.id))

    async def list_roles(self, skip: int = 0, limit: int = 100) -> List[Role]:
        return await self.role_repo.list(skip=skip, limit=limit)

    async def update_role(self, role_id: str, data: RoleUpdate) -> Role:
        role = await self.role_repo.get_by_id(role_id)
        if not role:
            raise NotFoundException("Role not found")
            
        update_data = data.model_dump(exclude_unset=True)
        if "name" in update_data and update_data["name"] != role.name:
            if await self.role_repo.get_by_name(update_data["name"]):
                raise BadRequestException(f"Role '{update_data['name']}' already exists.")
                
        for field, value in update_data.items():
            setattr(role, field, value)
            
        return await self.role_repo.update(role)

    async def delete_role(self, role_id: str) -> None:
        role = await self.role_repo.get_by_id(role_id)
        if not role:
            raise NotFoundException("Role not found")
        if role.name in ["Admin", "User"]:
            raise BadRequestException(f"Cannot delete system default role: {role.name}")
        await self.role_repo.delete(role)


    # --- Assignments ---

    async def assign_permissions_to_role(self, role_id: str, data: AssignPermissionsRequest) -> Role:
        role = await self.role_repo.get_by_id(role_id)
        if not role:
            raise NotFoundException("Role not found")
            
        permissions = await self.perm_repo.get_by_ids([str(pid) for pid in data.permission_ids])
        if len(permissions) != len(data.permission_ids):
             raise BadRequestException("One or more permissions not found")
             
        role.permissions = permissions
        return await self.role_repo.update(role)

    async def assign_roles_to_user(self, user_id: str, data: AssignRolesRequest) -> User:
        user = await self.user_repo.get_by_id(user_id)
        if not user:
            raise NotFoundException("User not found")
            
        # We need to manually load roles here if get_by_id doesn't include them
        from sqlalchemy.orm import selectinload
        from sqlalchemy import select
        query = select(User).where(User.id == user.id).options(selectinload(User.roles))
        user = (await self.db.execute(query)).scalar_one()

        roles = []
        for rid in data.role_ids:
            role = await self.role_repo.get_by_id(str(rid))
            if not role:
                 raise BadRequestException(f"Role {rid} not found")
            roles.append(role)
             
        user.roles = roles
        return await self.user_repo.update(user)
