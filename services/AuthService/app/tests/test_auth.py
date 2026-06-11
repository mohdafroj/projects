import pytest
from httpx import AsyncClient
from unittest.mock import patch, AsyncMock

@pytest.mark.asyncio
async def test_successful_login(client: AsyncClient):
    """Test successful login with captcha mock."""
    with patch('app.services.captcha_service.CaptchaService.verify_captcha', return_value=True):
        payload = {
            "username": "testadmin",
            "password": "Admin@123!",
            "captcha_id": "fake_id",
            "captcha_code": "1234"
        }
        response = await client.post(
            "/api/v1/auth/login",
            json=payload
        )
        assert response.status_code == 200
        data = response.json()
        assert "access_token" in data
        assert "refresh_token" in response.cookies
        assert "csrf_token" in response.cookies

@pytest.mark.asyncio
async def test_failed_login_captcha(client: AsyncClient):
    """Test login failure on invalid captcha."""
    with patch('app.services.captcha_service.CaptchaService.verify_captcha', return_value=False):
        payload = {
            "username": "testadmin",
            "password": "Admin@123!",
            "captcha_id": "fake_id",
            "captcha_code": "wrong"
        }
        response = await client.post(
            "/api/v1/auth/login",
            json=payload
        )
        assert response.status_code == 400
        assert "captcha" in response.json()["message"].lower()

@pytest.mark.asyncio
async def test_account_lockout(client: AsyncClient):
    """Test that account is locked after 5 failed attempts."""
    from app.db.redis import get_redis
    redis = await get_redis()
    await redis.flushdb()
    
    with patch('app.services.captcha_service.CaptchaService.verify_captcha', return_value=True):
        payload = {
            "username": "testuser",
            "password": "WrongPassword1!",
            "captcha_id": "fake_id",
            "captcha_code": "1234"
        }
        # Fail 4 times
        for _ in range(4):
            response = await client.post("/api/v1/auth/login", json=payload)
            
        # 5th time should trigger lockout
        response = await client.post("/api/v1/auth/login", json=payload)
        assert response.status_code == 403
        assert "locked" in response.json()["message"].lower()
