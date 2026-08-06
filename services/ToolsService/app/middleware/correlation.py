import uuid
from contextvars import ContextVar
from starlette.middleware.base import BaseHTTPMiddleware
from fastapi import Request

# Context variable to store the request ID for the duration of the request
request_id_ctx_var: ContextVar[str] = ContextVar("request_id", default=None)

def get_request_id() -> str:
    """
    Utility function to retrieve the current request ID from the context.
    """
    return request_id_ctx_var.get()

class CorrelationIDMiddleware(BaseHTTPMiddleware):
    """
    Middleware that assigns a unique X-Request-ID to every request.
    This ID is returned in the response headers and stored in context for logging.
    """
    async def dispatch(self, request: Request, call_next):
        # 1. Get or generate request ID
        request_id = request.headers.get("X-Request-ID", str(uuid.uuid4()))
        
        # 2. Store in context variable
        token = request_id_ctx_var.set(request_id)
        
        try:
            # 3. Process the request
            response = await call_next(request)
            
            # 4. Add to response headers
            response.headers["X-Request-ID"] = request_id
            return response
        finally:
            # 5. Clean up context variable
            request_id_ctx_var.reset(token)
