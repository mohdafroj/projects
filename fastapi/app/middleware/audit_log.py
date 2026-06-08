import time
from fastapi import Request
from starlette.middleware.base import BaseHTTPMiddleware
from app.db.session import AsyncSessionLocal
from app.services.audit_service import AuditService
from app.middleware.correlation import get_request_id
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
        
        # Try to get user_id from token if present
        user_id = None
        auth_header = request.headers.get("Authorization")
        if auth_header and auth_header.startswith("Bearer "):
            try:
                token = auth_header.split(" ")[1]
                payload = jwt.decode(token, settings.JWT_SECRET, algorithms=[settings.ALGORITHM])
                user_id = payload.get("sub")
            except:
                pass 
        
        # 2. Execute the request
        response = await call_next(request)
        
        # 3. Log after execution
        if is_state_changing:
            # Retrieve request_id from correlation context
            request_id = get_request_id()
            
            async with AsyncSessionLocal() as db:
                audit_service = AuditService(db)
                try:
                    await audit_service.log_action(
                        user_id=user_id,
                        action=action,
                        resource=resource,
                        status_code=response.status_code,
                        ip_address=ip_address,
                        user_agent=user_agent,
                        request_id=request_id
                    )
                    await db.commit()
                except Exception as e:
                    print(f"Failed to record audit log: {e}")
        
        return response
