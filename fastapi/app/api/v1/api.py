from fastapi import APIRouter
from app.api.v1.endpoints import auth, users, audit

api_router = APIRouter()
api_router.include_router(auth.router, prefix="/auth", tags=["Auth"])
api_router.include_router(users.router, prefix="/users", tags=["Users"])
api_router.include_router(audit.router, prefix="/audit-logs", tags=["Audit Logs"])

