from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse
from fastapi.exceptions import RequestValidationError
from starlette.exceptions import HTTPException as StarletteHTTPException
from app.db.session import engine, Base
# from app.auth.router import router as auth_router
from app.user.router import router as user_router

#import fastapi
#print(fastapi.__version__)
Base.metadata.create_all(bind=engine)

app = FastAPI()

@app.exception_handler(StarletteHTTPException)
async def http_exception_handler(request: Request, exc: StarletteHTTPException):
    if isinstance(exc.detail, dict) and "success" in exc.detail:
        return JSONResponse(status_code=exc.status_code, content=exc.detail)
    return JSONResponse(
        status_code=exc.status_code,
        content={"success": False, "message": str(exc.detail), "data": []},
    )

@app.exception_handler(RequestValidationError)
async def validation_exception_handler(request: Request, exc: RequestValidationError):
    return JSONResponse(
        status_code=422,
        content={"success": False, "message": "Validation error", "data": exc.errors()},
    )

#app.include_router(auth_router)
app.include_router(user_router)

@app.get("/")
def read_root():
    return {"message": "FastAPI + PostgreSQL + Docker 🚀"}
