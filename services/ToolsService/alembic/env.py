import asyncio
from logging.config import fileConfig
from sqlalchemy import pool
from sqlalchemy.engine import Connection
from sqlalchemy.ext.asyncio import async_engine_from_config
from alembic import context

from app.core.config import settings
from app.db.base import Base

# Import all models here for autogenerate support
import app.models.chat_room
import app.models.chat_message

config = context.config

# Set sqlalchemy.url from settings
config.set_main_option("sqlalchemy.url", settings.DATABASE_URL)

if config.config_file_name is not None:
    fileConfig(config.config_file_name)

target_metadata = Base.metadata

def run_migrations_offline() -> None:
    url = config.get_main_option("sqlalchemy.url")
    import re
    if url and "schema=" in url:
        url = re.sub(r"[?&]schema=[^&]+", "", url)
        url = url.rstrip("?").rstrip("&")
        
    context.configure(
        url=url,
        target_metadata=target_metadata,
        literal_binds=True,
        dialect_opts={"paramstyle": "named"},
    )

    with context.begin_transaction():
        context.run_migrations()

def do_run_migrations(connection: Connection) -> None:
    context.configure(connection=connection, target_metadata=target_metadata)

    with context.begin_transaction():
        context.run_migrations()

async def run_migrations_online() -> None:
    configuration = config.get_section(config.config_ini_section)
    url = configuration.get("sqlalchemy.url")
    import re
    connect_args = {}
    if url and "schema=" in url:
        schema_match = re.search(r"[?&]schema=([^&]+)", url)
        if schema_match:
            schema = schema_match.group(1)
            url = re.sub(r"[?&]schema=[^&]+", "", url)
            url = url.rstrip("?").rstrip("&")
            configuration["sqlalchemy.url"] = url
            connect_args["server_settings"] = {"search_path": schema}
            
    connectable = async_engine_from_config(
        configuration,
        prefix="sqlalchemy.",
        poolclass=pool.NullPool,
        connect_args=connect_args,
    )

    async with connectable.connect() as connection:
        await connection.run_sync(do_run_migrations)

    await connectable.dispose()

if context.is_offline_mode():
    run_migrations_offline()
else:
    asyncio.run(run_migrations_online())
