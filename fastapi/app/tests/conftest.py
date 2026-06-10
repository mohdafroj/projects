import asyncio
import os
import pytest
import pytest_asyncio
from httpx import AsyncClient, ASGITransport
from sqlalchemy.ext.asyncio import create_async_engine, async_sessionmaker, AsyncSession
from typing import AsyncGenerator

# Set test database and test environment variables before importing app
os.environ["DATABASE_URL"] = "postgresql+asyncpg://postgres:postgres@srv_db:5432/test_db"
# Use a separate redis DB for testing (DB 1 instead of 0)
os.environ["REDIS_URL"] = "redis://srv_redis:6379/1"
os.environ["ENVIRONMENT"] = "testing"

from app.main import app
from app.db.base import Base
from app.db.session import get_db
from app.core.security import get_password_hash, create_access_token
from app.models.user import User, Role, Permission

# Test DB Engine
test_engine = create_async_engine(os.environ["DATABASE_URL"], echo=False)
TestingSessionLocal = async_sessionmaker(
    bind=test_engine,
    class_=AsyncSession,
    expire_on_commit=False,
    autoflush=False,
    autocommit=False,
)

@pytest_asyncio.fixture(scope="session", autouse=True)
async def setup_database():
    """Create tables and seed initial test data."""
    async with test_engine.begin() as conn:
        await conn.run_sync(Base.metadata.drop_all)
        await conn.run_sync(Base.metadata.create_all)
    
    # Seed data
    async with TestingSessionLocal() as db:
        # Create permissions
        perms = [
            Permission(name="user:create", module="user"),
            Permission(name="user:view", module="user"),
            Permission(name="user:update", module="user"),
            Permission(name="user:delete", module="user"),
            Permission(name="role:manage", module="role"),
            Permission(name="permission:manage", module="permission"),
            Permission(name="audit:view", module="audit"),
        ]
        db.add_all(perms)
        await db.commit()
        
        # Create roles
        admin_role = Role(name="Admin", permissions=perms)
        user_role = Role(name="User", permissions=[perms[1]]) # only user:view
        db.add(admin_role)
        db.add(user_role)
        await db.commit()

        # Create test super admin
        admin_user = User(
            email="testadmin@example.com",
            username="testadmin",
            full_name="Test Admin",
            hashed_password=get_password_hash("Admin@123!"),
            is_active=True,
            is_verified=True,
            is_super_admin=True,
            roles=[admin_role]
        )
        db.add(admin_user)
        
        # Create normal test user
        normal_user = User(
            email="testuser@example.com",
            username="testuser",
            full_name="Test User",
            hashed_password=get_password_hash("User@123!"),
            is_active=True,
            is_verified=True,
            is_super_admin=False,
            roles=[user_role]
        )
        db.add(normal_user)
        await db.commit()
        
    yield
    
    async with test_engine.begin() as conn:
        await conn.run_sync(Base.metadata.drop_all)
    await test_engine.dispose()

@pytest_asyncio.fixture(autouse=True)
async def clear_redis():
    """Flush the Redis test database before each test to prevent rate limit interference."""
    from app.db.redis import get_redis
    redis = await get_redis()
    await redis.flushdb()
    yield

@pytest_asyncio.fixture
async def db_session() -> AsyncGenerator[AsyncSession, None]:
    async with TestingSessionLocal() as session:
        yield session

@pytest_asyncio.fixture
async def client(db_session: AsyncSession):
    """Provides an AsyncClient for FastAPI endpoint testing."""
    async def override_get_db():
        yield db_session
    
    app.dependency_overrides[get_db] = override_get_db
    
    # We must explicitly set base_url for relative URLs to work correctly
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as ac:
        yield ac
        
    app.dependency_overrides.clear()

@pytest_asyncio.fixture
async def admin_token(db_session: AsyncSession):
    """Returns a valid access token for the test admin user."""
    from sqlalchemy import select
    user = (await db_session.execute(select(User).where(User.username == "testadmin"))).scalar_one()
    return create_access_token(subject=user.id)

@pytest_asyncio.fixture
async def normal_token(db_session: AsyncSession):
    """Returns a valid access token for the normal test user."""
    from sqlalchemy import select
    user = (await db_session.execute(select(User).where(User.username == "testuser"))).scalar_one()
    return create_access_token(subject=user.id)
