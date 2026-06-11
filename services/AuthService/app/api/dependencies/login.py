from fastapi import Request, status
from fastapi.security import OAuth2PasswordRequestForm
from app.schemas.auth import LoginRequest
from app.core.exceptions import ValidationException
import json

async def get_login_data(request: Request) -> LoginRequest:
    """
    Unified dependency to handle both JSON and Form Data for login.
    Now supports captcha and optional mobile device metadata.
    """
    content_type = request.headers.get("Content-Type", "")
    
    if "application/json" in content_type:
        try:
            body = await request.json()
            return LoginRequest(**body)
        except Exception:
            raise ValidationException(message="Invalid JSON payload")
    
    # Fallback to Form Data (for Swagger UI / OAuth2 clients)
    try:
        form = await request.form()
        username = form.get("username")
        password = form.get("password")
        captcha_id = form.get("captcha_id")
        captcha_code = form.get("captcha_code")
        
        if not username or not password:
             raise ValidationException(message="Username and password are required")
            
        if not captcha_id or not captcha_code:
             raise ValidationException(message="Captcha ID and code are required")
            
        return LoginRequest(
            username=username, 
            password=password,
            captcha_id=captcha_id,
            captcha_code=captcha_code,
            device_id=form.get("device_id"),
            device_name=form.get("device_name"),
            platform=form.get("platform")
        )
    except Exception as e:
        if isinstance(e, ValidationException):
            raise e
        raise ValidationException(message=f"Invalid form data: {str(e)}")
