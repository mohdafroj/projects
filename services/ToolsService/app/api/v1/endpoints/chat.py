from fastapi import APIRouter
from fastapi.responses import StreamingResponse
from app.schemas.chat import StreamChatRequest
from app.services.ai_service import AIService

router = APIRouter()
ai_service = AIService()

@router.post("/stream")
async def stream_chat_endpoint(request: StreamChatRequest):
    return StreamingResponse(
        ai_service.generate_stream(
            prompt=request.prompt,
            system_prompt=request.system_prompt,
            model=request.model,
            temperature=request.temperature,
            max_tokens=request.max_tokens,
            history=request.history
        ),
        media_type="text/event-stream"
    )
