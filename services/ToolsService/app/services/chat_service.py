import asyncio
import random
from typing import Callable, Optional
from uuid import UUID
from sqlalchemy.ext.asyncio import AsyncSession
from openai import AsyncOpenAI

from app.core.config import settings
from app.db.session import AsyncSessionLocal
from app.repositories.chat_room_repository import ChatRoomRepository
from app.repositories.chat_message_repository import ChatMessageRepository
from app.schemas.chat_room import ChatRoomCreate
from app.schemas.chat_message import ChatMessageCreate, ChatMessageResponse

class ChatService:
    def __init__(self):
        self.room_repo = ChatRoomRepository()
        self.msg_repo = ChatMessageRepository()
        
        # Initialize OpenAI client if valid key exists
        self.api_key = settings.OPENAI_API_KEY
        self.has_real_key = bool(self.api_key and not self.api_key.startswith("YOUR_") and not self.api_key.startswith("placeholder") and len(self.api_key) > 10)
        if self.has_real_key:
            self.openai_client = AsyncOpenAI(api_key=self.api_key)
        else:
            self.openai_client = None

    async def get_rooms(self, db: AsyncSession):
        return await self.room_repo.get_all(db)

    async def get_room(self, db: AsyncSession, room_id: UUID):
        return await self.room_repo.get_by_id(db, room_id)

    async def create_room(self, db: AsyncSession, room_data: ChatRoomCreate):
        return await self.room_repo.create(db, room_data)

    async def get_message_history(self, db: AsyncSession, room_id: UUID, limit: int = 100):
        return await self.msg_repo.get_by_room_id(db, room_id, limit)

    async def save_message(self, db: AsyncSession, room_id: UUID, message_data: ChatMessageCreate):
        return await self.msg_repo.create(db, message_data, room_id)

    async def seed_initial_data(self, db: AsyncSession) -> None:
        """
        Seeds initial chat rooms and message history if the database is currently empty.
        """
        existing_rooms = await self.room_repo.get_all(db)
        if existing_rooms:
            print("Rooms already exist in database. Skipping seed.")
            return

        print("Seeding initial chat rooms and messages...")

        # 1. Create Rooms
        rooms_to_create = [
            {
                "key": "design",
                "name": "design-system",
                "type": "channel",
                "avatar": "🎨",
                "avatar_color": "from-pink-500 to-rose-500",
                "description": "Coordination space for our global UX component library and UI layouts."
            },
            {
                "key": "dev",
                "name": "development",
                "type": "channel",
                "avatar": "⚙️",
                "avatar_color": "from-cyan-500 to-blue-500",
                "description": "CI/CD checks, PR reviews, and engineering updates."
            },
            {
                "key": "sarah",
                "name": "Sarah (QA Lead)",
                "type": "dm",
                "avatar": "👩‍💻",
                "avatar_color": "from-emerald-500 to-teal-500",
                "description": "Quality assurance manager. Leads test-automation pipeline."
            },
            {
                "key": "alex",
                "name": "Alex (Systems Dev)",
                "type": "dm",
                "avatar": "👨‍💻",
                "avatar_color": "from-indigo-500 to-purple-500",
                "description": "Senior Infrastructure Engineer working on AWS orchestration."
            },
            {
                "key": "john",
                "name": "John (Product)",
                "type": "dm",
                "avatar": "💼",
                "avatar_color": "from-amber-500 to-orange-500",
                "description": "Product Manager. Oversees roadmaps and sprint planning."
            }
        ]

        created_rooms = {}
        for r_data in rooms_to_create:
            key = r_data.pop("key")
            room = await self.room_repo.create(db, ChatRoomCreate(**r_data))
            created_rooms[key] = room

        # 2. Seed Initial Messages
        initial_messages = {
            "design": [
                {
                    "sender_name": "Sarah (QA Lead)",
                    "sender_avatar": "👩‍💻",
                    "sender_color": "from-emerald-500 to-teal-500",
                    "text": "Did everyone review the Figma draft for the chat dashboard?",
                    "is_user": False
                },
                {
                    "sender_name": "Alex (Systems Dev)",
                    "sender_avatar": "👨‍💻",
                    "sender_color": "from-indigo-500 to-purple-500",
                    "text": "Yes, looks clean! The dark-theme glassmorphism style is sleek.",
                    "is_user": False
                }
            ],
            "dev": [
                {
                    "sender_name": "Alex (Systems Dev)",
                    "sender_avatar": "👨‍💻",
                    "sender_color": "from-indigo-500 to-purple-500",
                    "text": "Docker compose services are up and serving local dev servers.",
                    "is_user": False
                },
                {
                    "sender_name": "John (Product)",
                    "sender_avatar": "💼",
                    "sender_color": "from-amber-500 to-orange-500",
                    "text": "Great progress. Is MFE host loading remote bundles successfully?",
                    "is_user": False
                }
            ],
            "sarah": [
                {
                    "sender_name": "Sarah (QA Lead)",
                    "sender_avatar": "👩‍💻",
                    "sender_color": "from-emerald-500 to-teal-500",
                    "text": "Hi, can you verify the logout routing issue in the staging build?",
                    "is_user": False
                }
            ],
            "alex": [
                {
                    "sender_name": "Alex (Systems Dev)",
                    "sender_avatar": "👨‍💻",
                    "sender_color": "from-indigo-500 to-purple-500",
                    "text": "Hey! Let's debug the module federation share loading later.",
                    "is_user": False
                }
            ],
            "john": [
                {
                    "sender_name": "John (Product)",
                    "sender_avatar": "💼",
                    "sender_color": "from-amber-500 to-orange-500",
                    "text": "Good work on the updates, sprint retrospective is scheduled.",
                    "is_user": False
                }
            ]
        }

        for key, msgs in initial_messages.items():
            room = created_rooms.get(key)
            if room:
                for m_data in msgs:
                    await self.msg_repo.create(db, ChatMessageCreate(**m_data), room.id)

        print("Chat rooms and messages seeded successfully!")

    async def trigger_simulated_reply(self, room_id: UUID, user_message_text: str, broadcast_callback: Callable):
        """
        Launches a background task to generate and broadcast a response from the corresponding persona.
        """
        asyncio.create_task(self._generate_and_broadcast_reply(room_id, user_message_text, broadcast_callback))

    async def _generate_and_broadcast_reply(self, room_id: UUID, user_message_text: str, broadcast_callback: Callable):
        """
        Simulates typing delay, generates a response, persists it to DB using a new session,
        and broadcasts it via the websocket connection.
        """
        # 1. Wait to simulate reading/typing delay (1 to 2.5 seconds)
        await asyncio.sleep(random.uniform(1.0, 2.5))

        # 2. Open a new session to access the database inside background task
        async with AsyncSessionLocal() as db:
            try:
                room = await self.room_repo.get_by_id(db, room_id)
                if not room:
                    return

                # Establish Persona details
                name = room.name
                avatar = room.avatar or "👤"
                color = room.avatar_color or "from-slate-500 to-slate-700"

                # Define Persona instructions
                persona_info = ""
                if "sarah" in room.name.lower():
                    persona_info = "You are Sarah, a QA Lead. Keep answers brief, professional, and related to testing, automated test suites, or bugs."
                elif "alex" in room.name.lower():
                    persona_info = "You are Alex, a senior systems/infrastructure developer. Keep answers brief, professional, and related to Docker, Kubernetes, AWS, or backend engineering."
                elif "john" in room.name.lower():
                    persona_info = "You are John, a Product Manager. Keep answers brief, professional, and related to sprint schedules, project roadmap, and user stories."
                else:
                    persona_info = f"You are {room.name}, a helpful coworker. Keep replies short and professional."

                reply_text = ""

                # Try calling OpenAI API if a real key is present
                if self.has_real_key and self.openai_client:
                    try:
                        response = await self.openai_client.chat.completions.create(
                            model="gpt-4o",
                            messages=[
                                {"role": "system", "content": persona_info},
                                {"role": "user", "content": user_message_text}
                            ],
                            max_tokens=150,
                            temperature=0.7
                        )
                        reply_text = response.choices[0].message.content.strip()
                    except Exception as e:
                        print(f"OpenAI completion failed: {e}. Falling back to template simulation.")

                # Fallback to rich pre-written persona replies if AI failed/no-key
                if not reply_text:
                    replies_pool = []
                    if "sarah" in room.name.lower():
                        replies_pool = [
                            "I ran the unit tests on the auth branch, they look solid! Let's get it merged.",
                            "Did you double check if the logout routing is cleared in staging?",
                            "The automated cypress run failed. Let me debug the click target selector.",
                            "Let's sync up on the sprint retrospective later. I've got a couple of tickets to move to QA.",
                            "Excellent. I'm adding a regression test case for that login error you spotted."
                        ]
                    elif "alex" in room.name.lower():
                        replies_pool = [
                            "Awesome! I've updated the Docker configuration for srv_tools, it runs schema creation on startup.",
                            "Let's double-check the module federation shared library configurations later.",
                            "I'm configuring the Redis caching layer to speed up user sessions.",
                            "Got it. I'll check the service logs using docker-compose logs -f srv_tools.",
                            "The database indexes are applied. Let's run a query speed check."
                        ]
                    elif "john" in room.name.lower():
                        replies_pool = [
                            "Great. I will adjust the project roadmap to reflect these tools integration changes.",
                            "Sprint planning is coming up. Let's make sure the chat tickets are fully estimated.",
                            "Understood. Let's make sure our release notes document the new WebSocket chat support.",
                            "Is this change unblocking the other micro-frontend teams? Let's check with the team.",
                            "Thanks for the update. Let's catch up during tomorrow's sync."
                        ]
                    else:
                        replies_pool = [
                            "Got it, thanks for the update!",
                            "Sounds good. Let's sync up later on this.",
                            "Reviewing the details right now.",
                            "Let's discuss this during our morning standup.",
                            "Could you send over the link or docs for reference?"
                        ]
                    reply_text = random.choice(replies_pool)

                # Save generated message
                reply_schema = ChatMessageCreate(
                    sender_name=name,
                    sender_avatar=avatar,
                    sender_color=color,
                    text=reply_text,
                    is_user=False
                )
                db_message = await self.msg_repo.create(db, reply_schema, room_id)
                await db.commit()

                # Broadcast back to the room
                resp = ChatMessageResponse.model_validate(db_message)
                # Broadcast payload matching what client expects
                await broadcast_callback(resp.model_dump())

            except Exception as ex:
                await db.rollback()
                print(f"Failed to generate background reply: {ex}")
