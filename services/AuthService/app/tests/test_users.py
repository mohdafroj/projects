import pytest
from httpx import AsyncClient

@pytest.mark.asyncio
async def test_admin_list_users(client: AsyncClient, admin_token: str):
    """Admin should be able to list users."""
    response = await client.get(
        "/api/v1/users/",
        headers={"Authorization": f"Bearer {admin_token}"}
    )
    assert response.status_code == 200
    data = response.json()
    assert data["success"] is True
    assert len(data["data"]) >= 2  # Admin and Normal user at least

@pytest.mark.asyncio
async def test_normal_user_create_user_forbidden(client: AsyncClient, normal_token: str):
    """Normal user should NOT be able to create users (requires user:create)."""
    response = await client.post(
        "/api/v1/users/",
        headers={"Authorization": f"Bearer {normal_token}"},
        json={
            "email": "test@test.com", 
            "username": "test", 
            "password": "Password1!", 
            "full_name": "Test"
        }
    )
    assert response.status_code == 403
    assert "Missing required permission" in response.json()["message"]

@pytest.mark.asyncio
async def test_get_own_profile(client: AsyncClient, normal_token: str):
    """Users should be able to update/view their own profile via /me."""
    # We test PATCH since we don't have a GET /me
    response = await client.patch(
        "/api/v1/users/me",
        headers={"Authorization": f"Bearer {normal_token}"},
        json={"full_name": "Updated Name"}
    )
    assert response.status_code == 200
    assert response.json()["data"]["full_name"] == "Updated Name"

@pytest.mark.asyncio
async def test_admin_create_user(client: AsyncClient, admin_token: str):
    """Admin can create another user."""
    payload = {
        "email": "newuser@example.com",
        "username": "newuser",
        "password": "SecurePassword1!Uniq999", # Made unique to avoid pwned check failure
        "full_name": "New User",
        "is_super_admin": False
    }
    response = await client.post(
        "/api/v1/users/",
        headers={"Authorization": f"Bearer {admin_token}"},
        json=payload
    )
    assert response.status_code == 201
    assert response.json()["success"] is True
    assert response.json()["data"]["email"] == "newuser@example.com"

@pytest.mark.asyncio
async def test_weak_password_rejected(client: AsyncClient, admin_token: str):
    """Test OWASP password policy enforcement."""
    payload = {
        "email": "weak@example.com",
        "username": "weak",
        "password": "123", # Too short, no caps, no symbols
        "full_name": "Weak User"
    }
    response = await client.post(
        "/api/v1/users/",
        headers={"Authorization": f"Bearer {admin_token}"},
        json=payload
    )
    assert response.status_code == 422
    assert "Password must be at least 10 characters long" in str(response.json()["errors"])
