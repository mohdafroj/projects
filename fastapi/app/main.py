from fastapi import FastAPI
import fastapi
from app.db.session import engine, Base
from app.auth.router import router as auth_router
from app.user.router import router as user_router

print(fastapi.__version__)

# Create tables
#Base.metadata.create_all(bind=engine)

app = FastAPI()
app.include_router(auth_router)
app.include_router(user_router)


@app.get("/")
def read_root():
    return {"message": "FastAPI + PostgreSQL + Docker 🚀"}
