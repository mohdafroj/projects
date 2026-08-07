from typing import Generic, TypeVar, Optional
from pydantic import BaseModel

T = TypeVar("T")

class IResponse(BaseModel, Generic[T]):
    success: bool = True
    message: str = "Operation successful"
    data: Optional[T] = None
