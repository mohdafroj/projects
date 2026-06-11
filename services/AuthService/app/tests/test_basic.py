import pytest
from httpx import AsyncClient

@pytest.mark.asyncio
async def test_health_check(client: AsyncClient):
    """Test the public health check endpoint."""
    response = await client.get("/")
    assert response.status_code == 200
    data = response.json()
    assert data["success"] is True
    assert data["message"] == "Service is healthy"
    assert data["data"]["environment"] == "testing"

@pytest.mark.asyncio
async def test_unauthorized_access(client: AsyncClient):
    """Ensure protected routes reject unauthenticated requests."""
    response = await client.get("/api/v1/users/")
    assert response.status_code == 401
    assert response.json()["message"] == "Not authenticated"

@pytest.mark.asyncio
async def test_captcha_generation(client: AsyncClient):
    """Test captcha generation endpoint."""
    response = await client.get("/api/v1/auth/captcha")
    assert response.status_code == 200
    assert "X-Captcha-ID" in response.headers
    assert response.headers["content-type"] == "image/png"
