from fastapi import APIRouter
from app.api.v1.endpoints import chat, chat_rooms, chat_ws

api_router = APIRouter()
api_router.include_router(chat.router, prefix="/chat", tags=["AI Chatbot"])
api_router.include_router(chat_rooms.router, prefix="/chat", tags=["Real-time Chat"])
api_router.include_router(chat_ws.router, prefix="/chat", tags=["Real-time Chat WebSockets"])
