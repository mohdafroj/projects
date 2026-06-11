from fastapi import Request, HTTPException, status
from app.db.redis import get_redis
import time

class RateLimiter:
    def __init__(self, times: int, seconds: int):
        self.times = times
        self.seconds = seconds

    async def __call__(self, request: Request):
        # Use client IP as the key
        key = f"rate_limit:{request.client.host}:{request.url.path}"
        redis = await get_redis()
        
        # Get current count
        current = await redis.get(key)
        
        if current is not None and int(current) >= self.times:
            # Get TTL to tell the user how long to wait
            ttl = await redis.ttl(key)
            
            # Format a clear message
            if ttl >= 60:
                time_str = f"{round(ttl/60, 1)} minutes"
            else:
                time_str = f"{ttl} seconds"
                
            raise HTTPException(
                status_code=status.HTTP_429_TOO_MANY_REQUESTS,
                detail=f"Too many requests. Please try again in {time_str}."
            )
        
        # Increment and set expiry if new
        async with redis.pipeline(transaction=True) as pipe:
            await pipe.incr(key)
            if current is None:
                await pipe.expire(key, self.seconds)
            await pipe.execute()
        
        return True
