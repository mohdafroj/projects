import pytest
from httpx import AsyncClient, ASGITransport
from app.main import app

@pytest.mark.asyncio
async def test_list_rooms():
    """
    Tests that we can successfully list chat rooms and that the seeded rooms exist.
    """
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        response = await ac.get("/api/v1/chat/rooms")
    assert response.status_code == 200
    res_json = response.json()
    assert res_json["success"] is True
    assert len(res_json["data"]) > 0
    
    room_names = [r["name"] for r in res_json["data"]]
    assert "design-system" in room_names
    assert "development" in room_names

@pytest.mark.asyncio
async def test_get_messages():
    """
    Tests fetching message history for a seeded room.
    """
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        # 1. Fetch rooms list to get room ID
        rooms_res = await ac.get("/api/v1/chat/rooms")
        rooms = rooms_res.json()["data"]
        design_room = next(r for r in rooms if r["name"] == "design-system")
        room_id = design_room["id"]

        # 2. Fetch messages for that room
        messages_res = await ac.get(f"/api/v1/chat/rooms/{room_id}/messages")
        
    assert messages_res.status_code == 200
    msgs_json = messages_res.json()
    assert msgs_json["success"] is True
    assert len(msgs_json["data"]) > 0
    # First message in design-system was seeded from Sarah
    assert msgs_json["data"][0]["sender_name"] == "Sarah (QA Lead)"
