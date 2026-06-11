from typing import Any, Dict, Optional
from fastapi import status

class AppException(Exception):
    def __init__(
        self, 
        status_code: int, 
        message: str, 
        data: Optional[Any] = None,
        errors: Optional[Any] = None
    ):
        self.status_code = status_code
        self.message = message
        self.data = data
        self.errors = errors

class UnauthorizedException(AppException):
    def __init__(self, message: str = "Unauthorized", data: Optional[Any] = None):
        super().__init__(status.HTTP_401_UNAUTHORIZED, message, data)

class ForbiddenException(AppException):
    def __init__(self, message: str = "Forbidden", data: Optional[Any] = None):
        super().__init__(status.HTTP_403_FORBIDDEN, message, data)

class NotFoundException(AppException):
    def __init__(self, message: str = "Not Found", data: Optional[Any] = None):
        super().__init__(status.HTTP_404_NOT_FOUND, message, data)

class ValidationException(AppException):
    def __init__(self, message: str = "Validation Error", errors: Optional[Any] = None):
        super().__init__(status.HTTP_422_UNPROCESSABLE_ENTITY, message, errors=errors)

class BadRequestException(AppException):
    def __init__(self, message: str = "Bad Request", data: Optional[Any] = None):
        super().__init__(status.HTTP_400_BAD_REQUEST, message, data)
