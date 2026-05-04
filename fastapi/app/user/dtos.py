from pydantic import BaseModel

class UserCreateDTO(BaseModel):
    name: str
    email: str
    username: str
    password: str
    status: bool = True