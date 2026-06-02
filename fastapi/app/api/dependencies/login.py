from fastapi import Request, HTTPException, status
from fastapi.security import OAuth2PasswordRequestForm
from app.schemas.auth import LoginRequest
import json

async def get_login_data(request: Request) -> LoginRequest:
    """
    Unified dependency to handle both JSON and Form Data for login.
    """
    content_type = request.headers.get("Content-Type", "")
    
    if "application/json" in content_type:
        try:
            body = await request.json()
            return LoginRequest(**body)
        except Exception:
            raise HTTPException(
                status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
                detail="Invalid JSON payload"
            )
    
    # Fallback to Form Data (for Swagger UI / OAuth2 clients)
    try:
        form = await request.form()
        username = form.get("username")
        password = form.get("password")
        
        if not username or not password:
             raise HTTPException(
                status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
                detail="Username and password are required"
            )
            
        return LoginRequest(username=username, password=password)
    except Exception:
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail="Invalid form data"
        )
