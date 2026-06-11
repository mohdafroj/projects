import pytest
from httpx import AsyncClient

@pytest.mark.asyncio
async def test_list_roles(client: AsyncClient, admin_token: str):
    """Admin can list roles."""
    response = await client.get(
        "/api/v1/rbac/roles",
        headers={"Authorization": f"Bearer {admin_token}"}
    )
    assert response.status_code == 200
    assert response.json()["success"] is True

@pytest.mark.asyncio
async def test_create_role(client: AsyncClient, admin_token: str):
    """Admin can create a new role."""
    response = await client.post(
        "/api/v1/rbac/roles",
        headers={"Authorization": f"Bearer {admin_token}"},
        json={"name": "Manager", "description": "Test role"}
    )
    assert response.status_code == 201
    data = response.json()["data"]
    assert data["name"] == "Manager"

@pytest.mark.asyncio
async def test_delete_default_role_fails(client: AsyncClient, admin_token: str):
    """Admin cannot delete default roles (Admin/User)."""
    # First get the Admin role ID
    response = await client.get(
        "/api/v1/rbac/roles",
        headers={"Authorization": f"Bearer {admin_token}"}
    )
    admin_role_id = next(r["id"] for r in response.json()["data"] if r["name"] == "Admin")
    
    # Try to delete it
    response = await client.delete(
        f"/api/v1/rbac/roles/{admin_role_id}",
        headers={"Authorization": f"Bearer {admin_token}"}
    )
    assert response.status_code == 400
    assert "Cannot delete system default role" in response.json()["message"]

@pytest.mark.asyncio
async def test_normal_user_rbac_forbidden(client: AsyncClient, normal_token: str):
    """Normal user cannot access RBAC APIs."""
    response = await client.get(
        "/api/v1/rbac/roles",
        headers={"Authorization": f"Bearer {normal_token}"}
    )
    assert response.status_code == 403
