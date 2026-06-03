import sqlalchemy as sa
import pandas as pd
from sqlalchemy import create_engine, inspect, text
import json
# Install Libraries: (pip install pandas pymysql sqlalchemy psycopg2-binary)
# Run python migrate_mysql_to_pg.py
# Connection strings
# Update these if your hostnames or credentials change
mysql_url = "mysql+pymysql://root@172.24.0.6/sds_db"
pg_url = "postgresql://postgres:postgres@172.24.0.2/sds_db"

def migrate():
    """
    Robust migration script from MySQL to PostgreSQL.
    Handles:
    - 300+ tables
    - JSON field conversion
    - NUL byte cleanup
    - Schema constraints disabling during migration
    """
    print("Starting migration...")
    mysql_engine = create_engine(mysql_url)
    pg_engine = create_engine(pg_url)
    
    inspector = inspect(mysql_engine)
    tables = inspector.get_table_names()
    
    print(f"Found {len(tables)} tables in MySQL.")
    
    # Disable constraints on Postgres to allow inserting data in any order
    with pg_engine.connect() as conn:
        conn.execute(text("SET session_replication_role = 'replica';"))
        conn.commit()

    for table in tables:
        # Skip volatile/large temporary tables if necessary
        if table in ['cache', 'sessions', 'job_batches']:
             print(f"Skipping volatile table: {table}")
             continue

        print(f"Migrating table: {table}...", end=" ", flush=True)
        try:
            # Read from MySQL
            df = pd.read_sql_table(table, mysql_engine)
            
            # Clean and prepare data
            for col in df.columns:
                # Target columns that might contain non-standard data
                if df[col].dtype == 'object':
                    # Fix NUL characters (common in MySQL binary/text exports)
                    df[col] = df[col].apply(lambda x: x.replace('\x00', '') if isinstance(x, str) else x)
                    
                    # Convert internal dicts/lists to JSON strings for Postgres
                    mask = df[col].apply(lambda x: isinstance(x, (dict, list)))
                    if mask.any():
                        df[col] = df[col].apply(lambda x: json.dumps(x) if isinstance(x, (dict, list)) else x)

            # Drop existing table with CASCADE to clear old schemas/relationships
            with pg_engine.connect() as conn:
                conn.execute(text(f'DROP TABLE IF EXISTS "{table}" CASCADE'))
                conn.commit()

            # Write to Postgres
            # chunksize=1000 ensures memory stability for large tables
            df.to_sql(table, pg_engine, if_exists='replace', index=False, chunksize=1000)
            print("Done.")
        except Exception as e:
            print(f"Failed: {e}")

    # Re-enable constraints
    with pg_engine.connect() as conn:
        conn.execute(text("SET session_replication_role = 'origin';"))
        conn.commit()
    print("Migration finished.")

if __name__ == "__main__":
    migrate()
