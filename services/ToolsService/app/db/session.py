from sqlalchemy.ext.asyncio import create_async_engine, async_sessionmaker, AsyncSession
import re
from app.core.config import settings

db_url = settings.DATABASE_URL
connect_args = {}
if db_url and "schema=" in db_url:
    schema_match = re.search(r"[?&]schema=([^&]+)", db_url)
    if schema_match:
        schema = schema_match.group(1)
        # Remove schema from URL to avoid asyncpg connect error
        db_url = re.sub(r"[?&]schema=[^&]+", "", db_url)
        # Handle trailing ? or & if any
        db_url = db_url.rstrip("?").rstrip("&")
        connect_args["server_settings"] = {"search_path": schema}

engine = create_async_engine(
    db_url,
    echo=settings.DEBUG,
    future=True,
    pool_pre_ping=True,
    connect_args=connect_args,
)

AsyncSessionLocal = async_sessionmaker(
    bind=engine,
    class_=AsyncSession,
    expire_on_commit=False,
    autoflush=False,
    autocommit=False,
)

async def get_db():
    async with AsyncSessionLocal() as session:
        try:
            yield session
            await session.commit()
        except Exception:
            await session.rollback()
            raise
        finally:
            await session.close()
