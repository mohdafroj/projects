from typing import List, Optional
from uuid import UUID
from datetime import datetime
from pydantic import BaseModel, ConfigDict

# --- Permission Schemas ---

class PermissionBase(BaseModel):
    name: str
    description: Optional[str] = None
    module: str

class PermissionCreate(PermissionBase):
    pass

class PermissionUpdate(BaseModel):
    name: Optional[str] = None
    description: Optional[str] = None
    module: Optional[str] = None

class PermissionResponse(PermissionBase):
    id: UUID
    model_config = ConfigDict(from_attributes=True)


# --- Role Schemas ---

class RoleBase(BaseModel):
    name: str
    description: Optional[str] = None
    is_active: bool = True

class RoleCreate(RoleBase):
    pass

class RoleUpdate(BaseModel):
    name: Optional[str] = None
    description: Optional[str] = None
    is_active: Optional[bool] = None

class RoleResponse(RoleBase):
    id: UUID
    permissions: List[PermissionResponse] = []
    model_config = ConfigDict(from_attributes=True)


# --- Assignment Schemas ---

class AssignPermissionsRequest(BaseModel):
    permission_ids: List[UUID]

class AssignRolesRequest(BaseModel):
    role_ids: List[UUID]
