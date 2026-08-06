from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse
from fastapi.exceptions import RequestValidationError
from fastapi.encoders import jsonable_encoder
from starlette.exceptions import HTTPException as StarletteHTTPException
from starlette.middleware.cors import CORSMiddleware
from app.middleware.audit_log import AuditLogMiddleware
from app.middleware.security import SecurityHeadersMiddleware
from app.middleware.correlation import CorrelationIDMiddleware

from app.core.config import settings
from app.core.exceptions import AppException
from app.core.logger import setup_logging
from app.api.v1.api import api_router

def get_application() -> FastAPI:
    setup_logging()
    
    _app = FastAPI(
        title=settings.PROJECT_NAME,
        debug=settings.DEBUG,
    )

    # Set all CORS enabled origins
    if settings.BACKEND_CORS_ORIGINS:
        _app.add_middleware(
            CORSMiddleware,
            allow_origins=[str(origin) for origin in settings.BACKEND_CORS_ORIGINS],
            allow_credentials=True,
            allow_methods=["*"],
            allow_headers=["*"],
        )

    # Middleware Stack (Outer to Inner)
    _app.add_middleware(CorrelationIDMiddleware)
    _app.add_middleware(SecurityHeadersMiddleware)
    _app.add_middleware(AuditLogMiddleware)

    # Exception Handlers
    @_app.exception_handler(AppException)
    async def app_exception_handler(request: Request, exc: AppException):
        return JSONResponse(
            status_code=exc.status_code,
            content={
                "success": False,
                "message": exc.message,
                "data": exc.data,
                "errors": exc.errors
            },
        )

    @_app.exception_handler(StarletteHTTPException)
    async def http_exception_handler(request: Request, exc: StarletteHTTPException):
        return JSONResponse(
            status_code=exc.status_code,
            content={
                "success": False,
                "message": str(exc.detail),
                "data": None
            },
        )

    @_app.exception_handler(RequestValidationError)
    async def validation_exception_handler(request: Request, exc: RequestValidationError):
        return JSONResponse(
            status_code=422,
            content={
                "success": False,
                "message": "Validation Error",
                "errors": jsonable_encoder(exc.errors())
            },
        )

    _app.include_router(api_router, prefix=settings.API_V1_STR)

    @_app.get("/", tags=["Health Check"])
    async def health_check():
        return {
            "success": True,
            "message": "Service is healthy",
            "data": {
                "project_name": settings.PROJECT_NAME,
                "environment": settings.ENVIRONMENT
            }
        }

    return _app

app = get_application()
