import uuid
from app.utils.captcha import captcha_tool
from app.db.redis import get_redis

class CaptchaService:
    @staticmethod
    async def generate_captcha():
        captcha_id = str(uuid.uuid4())
        text = captcha_tool.generate_random_text()
        image_data = captcha_tool.create_captcha_image(text)
        
        # Store in Redis with 5 minutes expiry
        redis = await get_redis()
        await redis.set(f"captcha:{captcha_id}", text.upper(), ex=300)
        
        return captcha_id, image_data

    @staticmethod
    async def verify_captcha(captcha_id: str, code: str) -> bool:
        if not captcha_id or not code:
            return False
            
        redis = await get_redis()
        stored_code = await redis.get(f"captcha:{captcha_id}")
        
        if not stored_code:
            return False
            
        # Delete after use (one-time use)
        await redis.delete(f"captcha:{captcha_id}")
        
        return stored_code == code.upper()
