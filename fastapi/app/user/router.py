from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session
from app.db.session import get_db
from app.user import controller
from app.user.dtos import UserCreateDTO

router = APIRouter(prefix="/users", tags=["Users"])

@router.post("/")
def create(body: UserCreateDTO, db: Session = Depends(get_db)):
    return controller.create_user(body, db)

@router.get("/")
def list_users(db: Session = Depends(get_db)):
    return controller.get_all_users(db)
