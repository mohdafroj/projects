from fastapi import APIRouter, HTTPException, Depends
from sqlalchemy.orm import Session
from app.db.session import get_db
from app.models.user import User
from app.core.security import hash_password
router = APIRouter(prefix="/users", tags=["Users"])

@router.post("/dummy-user")
def create_test_user(db: Session = Depends(get_db)):
    # ✅ create user
    try:
        test_user = User(
            name="Admin User",
            email="admin@example.com",
            username="admin",
            hashed_password=hash_password("admin")
        )

        db.add(test_user)
        db.commit()
        db.refresh(test_user)

        return {
            "message": "User created successfully",
            "user_id": test_user.id
        }

    except Exception as e:
        db.rollback()
        raise HTTPException(status_code=500, detail=str(e))
