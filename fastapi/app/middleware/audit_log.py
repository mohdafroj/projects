import time
from fastapi import Request
from starlette.middleware.base import BaseHTTPMiddleware
from app.db.session import AsyncSessionLocal
from app.services.audit_service import AuditService
from jose import jwt
from app.core.config import settings

class AuditLogMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request: Request, call_next):
        # 1. Capture request details before execution
        action = request.method
        resource = str(request.url.path)
        ip_address = request.client.host if request.client else None
        user_agent = request.headers.get("user-agent")
        
        # Only log state-changing operations
        is_state_changing = action in ["POST", "PATCH", "PUT", "DELETE"]
        
        # Try to get user_id from token if present (manual decode for middleware)
        user_id = None
        auth_header = request.headers.get("Authorization")
        if auth_header and auth_header.startswith("Bearer "):
            try:
                token = auth_header.split(" ")[1]
                payload = jwt.decode(token, settings.JWT_SECRET, algorithms=[settings.ALGORITHM])
                user_id = payload.get("sub")
            except:
                pass # Token invalid or expired, ignore here
        
        # 2. Execute the request
        response = await call_next(request)
        
        # 3. Log after execution (to get status code)
        if is_state_changing:
            # We use a new session specifically for the audit log to ensure it's recorded
            # even if the main request session rolls back.
            async with AsyncSessionLocal() as db:
                audit_service = AuditService(db)
                try:
                    await audit_service.log_action(
                        user_id=user_id,
                        action=action,
                        resource=resource,
                        status_code=response.status_code,
                        ip_address=ip_address,
                        user_agent=user_agent
                    )
                    await db.commit()
                except Exception as e:
                    # In production, you might log this to a file or sentry
                    print(f"Failed to record audit log: {e}")
        
        return response
