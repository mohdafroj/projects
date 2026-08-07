from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession
from uuid import UUID

from app.db.session import get_db
from app.schemas.response import IResponse
from app.schemas.chat_room import ChatRoomCreate, ChatRoomResponse
from app.schemas.chat_message import ChatMessageResponse
from app.services.chat_service import ChatService

router = APIRouter()
chat_service = ChatService()

@router.get("/rooms", response_model=IResponse[list[ChatRoomResponse]])
async def list_rooms(db: AsyncSession = Depends(get_db)):
    """
    Retrieves all available chat rooms.
    """
    rooms = await chat_service.get_rooms(db)
    return IResponse(
        success=True,
        message="Rooms retrieved successfully",
        data=rooms
    )

@router.post("/rooms", response_model=IResponse[ChatRoomResponse], status_code=status.HTTP_201_CREATED)
async def create_room(room_in: ChatRoomCreate, db: AsyncSession = Depends(get_db)):
    """
    Creates a new chat room.
    """
    room = await chat_service.create_room(db, room_in)
    return IResponse(
        success=True,
        message="Room created successfully",
        data=room
    )

@router.get("/rooms/{room_id}/messages", response_model=IResponse[list[ChatMessageResponse]])
async def get_messages(room_id: UUID, db: AsyncSession = Depends(get_db)):
    """
    Retrieves historical messages for a specific room.
    """
    room = await chat_service.get_room(db, room_id)
    if not room:
        raise HTTPException(status_code=404, detail="Chat room not found")
    messages = await chat_service.get_message_history(db, room_id)
    return IResponse(
        success=True,
        message="Message history retrieved successfully",
        data=messages
    )
