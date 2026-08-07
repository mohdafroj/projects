from fastapi import APIRouter, WebSocket, WebSocketDisconnect
from fastapi.encoders import jsonable_encoder
from uuid import UUID
from datetime import datetime
import asyncio

from app.db.session import AsyncSessionLocal
from app.schemas.chat_message import ChatMessageCreate, ChatMessageResponse
from app.services.chat_service import ChatService

router = APIRouter()
chat_service = ChatService()

class ConnectionManager:
    def __init__(self):
        # Dict mapping room_id (as string) to list of active WebSockets
        self.active_connections: dict[str, list[WebSocket]] = {}

    async def connect(self, websocket: WebSocket, room_id: str):
        await websocket.accept()
        if room_id not in self.active_connections:
            self.active_connections[room_id] = []
        self.active_connections[room_id].append(websocket)
        print(f"WebSocket client connected to room {room_id}. Active room connections: {len(self.active_connections[room_id])}")

    def disconnect(self, websocket: WebSocket, room_id: str):
        if room_id in self.active_connections:
            self.active_connections[room_id].remove(websocket)
            if not self.active_connections[room_id]:
                del self.active_connections[room_id]
        print(f"WebSocket client disconnected from room {room_id}")

    async def broadcast_json(self, data: dict, room_id: str):
        if room_id in self.active_connections:
            for connection in self.active_connections[room_id]:
                try:
                    await connection.send_json(data)
                except Exception as e:
                    print(f"Failed to send websocket message: {e}")

manager = ConnectionManager()

def format_broadcast_message(msg) -> dict:
    """
    Enriches and formats a message object to be fully compatible with both snake_case
    and the React frontend camelCase schemas, formatting the timestamp as 'HH:MM AM/PM'.
    """
    created_at_dt = msg.created_at
    if isinstance(created_at_dt, str):
        try:
            created_at_dt = datetime.fromisoformat(created_at_dt)
        except Exception:
            created_at_dt = datetime.now()
            
    timestamp_str = created_at_dt.strftime("%I:%M %p") if created_at_dt else datetime.now().strftime("%I:%M %p")
    
    return {
        "id": str(msg.id),
        "roomId": str(msg.room_id),
        "room_id": str(msg.room_id),
        "senderName": msg.sender_name,
        "sender_name": msg.sender_name,
        "senderAvatar": msg.sender_avatar,
        "sender_avatar": msg.sender_avatar,
        "senderColor": msg.sender_color,
        "sender_color": msg.sender_color,
        "text": msg.text,
        "isUser": msg.is_user,
        "is_user": msg.is_user,
        "timestamp": timestamp_str,
        "created_at": jsonable_encoder(msg.created_at),
        "updated_at": jsonable_encoder(msg.updated_at),
    }

@router.websocket("/ws/{room_id}")
async def websocket_chat_endpoint(websocket: WebSocket, room_id: UUID):
    room_id_str = str(room_id)
    await manager.connect(websocket, room_id_str)
    
    try:
        while True:
            # Receive message as JSON
            data = await websocket.receive_json()
            
            # Map camelCase to snake_case for the schema
            message_create = ChatMessageCreate(
                sender_name=data.get("senderName") or data.get("sender_name", "Anonymous"),
                sender_avatar=data.get("senderAvatar") or data.get("sender_avatar", "👤"),
                sender_color=data.get("senderColor") or data.get("sender_color", "from-slate-500 to-slate-700"),
                text=data.get("text", ""),
                is_user=data.get("isUser") or data.get("is_user", False)
            )
            
            if not message_create.text.strip():
                continue

            # Save the message in the database using a dedicated session
            async with AsyncSessionLocal() as db:
                try:
                    db_msg = await chat_service.save_message(db, room_id, message_create)
                    await db.commit()
                    # Refresh to populate auto-generated audit fields like created_at
                    await db.refresh(db_msg)
                    
                    # Prepare the broadcast payload
                    broadcast_payload = format_broadcast_message(db_msg)
                except Exception as db_err:
                    await db.rollback()
                    print(f"Error saving chat message to database: {db_err}")
                    continue

            # Broadcast the saved message to all clients in the room
            await manager.broadcast_json(broadcast_payload, room_id_str)

            # If user sent it, trigger persona chatbot reply in the background
            if message_create.is_user:
                async def broadcast_reply_cb(payload: dict):
                    # Fetch fresh message or construct payload formatting
                    # Payload in trigger_simulated_reply is formatted via ChatMessageResponse
                    # We convert it using our format_broadcast_message format helper:
                    class FakeMessage:
                        def __init__(self, d):
                            self.id = d["id"]
                            self.room_id = d["room_id"]
                            self.sender_name = d["sender_name"]
                            self.sender_avatar = d["sender_avatar"]
                            self.sender_color = d["sender_color"]
                            self.text = d["text"]
                            self.is_user = d["is_user"]
                            self.created_at = d.get("created_at") or datetime.now()
                            self.updated_at = d.get("updated_at") or datetime.now()
                    
                    fake_msg = FakeMessage(payload)
                    enriched = format_broadcast_message(fake_msg)
                    await manager.broadcast_json(enriched, room_id_str)

                await chat_service.trigger_simulated_reply(
                    room_id=room_id,
                    user_message_text=message_create.text,
                    broadcast_callback=broadcast_reply_cb
                )

    except WebSocketDisconnect:
        manager.disconnect(websocket, room_id_str)
    except Exception as e:
        print(f"WebSocket connection error on room {room_id_str}: {e}")
        manager.disconnect(websocket, room_id_str)
