from datetime import datetime, timedelta, timezone
from typing import Any, Union, Optional, Dict
from jose import jwt, jwe, JWTError
from passlib.context import CryptContext
from app.core.config import settings

# Passlib/Bcrypt 4.0+ compatibility fix
import logging
logging.getLogger('passlib').setLevel(logging.ERROR)

pwd_context = CryptContext(schemes=["bcrypt"], deprecated="auto")

def _get_encryption_key() -> bytes:
    """Returns a 32-byte key for AES256GCM encryption."""
    # We use the first 32 characters of the JWT secret to ensure it's a valid AES key length
    return settings.JWT_SECRET[:32].encode('utf-8')

def _create_nested_token(payload: Dict) -> str:
    """Creates a signed JWT and wraps it in an encrypted JWE."""
    # 1. Sign
    signed_jwt = jwt.encode(payload, settings.JWT_SECRET, algorithm=settings.ALGORITHM)
    # 2. Encrypt
    encrypted_jwe = jwe.encrypt(
        signed_jwt.encode('utf-8'), 
        _get_encryption_key(), 
        algorithm='dir', 
        encryption='A256GCM'
    )
    return encrypted_jwe.decode('utf-8')

def decode_nested_token(token: str) -> Dict:
    """Decrypts a JWE and verifies the signed JWT inside."""
    try:
        # 1. Decrypt
        decrypted_jwt = jwe.decrypt(token.encode('utf-8'), _get_encryption_key()).decode('utf-8')
        # 2. Verify Signature and Expiration
        payload = jwt.decode(decrypted_jwt, settings.JWT_SECRET, algorithms=[settings.ALGORITHM])
        return payload
    except Exception as e:
        # Catch any decryption or JWT verification errors
        raise JWTError(f"Token decryption or validation failed: {str(e)}")

def create_access_token(subject: Union[str, Any], expires_delta: timedelta = None) -> str:
    if expires_delta:
        expire = datetime.now(timezone.utc) + expires_delta
    else:
        expire = datetime.now(timezone.utc) + timedelta(
            minutes=settings.ACCESS_TOKEN_EXPIRE_MINUTES
        )
    to_encode = {"exp": expire, "sub": str(subject), "type": "access"}
    return _create_nested_token(to_encode)

def create_refresh_token(subject: Union[str, Any], expires_delta: timedelta = None) -> str:
    if expires_delta:
        expire = datetime.now(timezone.utc) + expires_delta
    else:
        expire = datetime.now(timezone.utc) + timedelta(
            days=settings.REFRESH_TOKEN_EXPIRE_DAYS
        )
    to_encode = {"exp": expire, "sub": str(subject), "type": "refresh"}
    return _create_nested_token(to_encode)

def verify_password(plain_password: str, hashed_password: str) -> bool:
    return pwd_context.verify(plain_password, hashed_password)

def get_password_hash(password: str) -> str:
    return pwd_context.hash(password)
