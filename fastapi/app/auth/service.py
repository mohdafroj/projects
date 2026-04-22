from app.core.security import verify_password, create_access_token
from sqlalchemy.orm import Session
from app.models.user import User

def authenticate_user(username: str, password: str, db: Session):
    user = db.query(User).filter(User.username == username).first()
    if not user:
        return None
    if not verify_password(password, user.hashed_password):
        return None
    return user

def login_user(username: str, password: str, db: Session):
    user = authenticate_user(username, password, db)
    if not user:
        return None
    token = create_access_token({"sub": username})
    return token