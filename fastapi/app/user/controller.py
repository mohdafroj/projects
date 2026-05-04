from fastapi import HTTPException
from app.user.dtos import UserCreateDTO
from app.user.models import UserModel
from sqlalchemy.orm import Session
from sqlalchemy.exc import IntegrityError
from app.core.security import hash_password

def _serialize_user(user: UserModel):
    return {
        "id": user.id,
        "name": user.name,
        "email": user.email,
        "username": user.username,
        "status": user.status,
        "created_at": user.created_at.isoformat() if user.created_at else None,
        "updated_at": user.updated_at.isoformat() if user.updated_at else None,
    }


def create_user(body: UserCreateDTO, db: Session):
    user_data = body.model_dump()
    try:
        user_data["hashed_password"] = hash_password(user_data["password"])
        if "password" in user_data:
            del user_data["password"]
            
        user = UserModel(**user_data)
        db.add(user)
        db.commit()
        db.refresh(user)
        return {
            "success": True,
            "message": "User created successfully",
            "data": _serialize_user(user)
        }
    except IntegrityError:
        db.rollback()
        raise HTTPException(
            status_code=400,
            detail={
                "success": False,
                "message": "User with this email or username already exists",
                "data": []
            }
        )
    except Exception as e:
        db.rollback()
        raise HTTPException(
            status_code=500,
            detail={
                "success": False,
                "message": str(e),
                "data": []
            }
        )


def get_all_users(db: Session):
    users = db.query(UserModel).all()
    return {
        "success": True,
        "message": "Users retrieved successfully",
        "data": [_serialize_user(user) for user in users]
    }
