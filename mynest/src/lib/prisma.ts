import 'dotenv/config';
import { PrismaPg } from '@prisma/adapter-pg';
import { PrismaClient } from '../generated/prisma/client';
import { Pool } from 'pg';

const pool = new Pool({
    //connectionString: process.env.DATABASE_URL,
    host: process.env.POSTGRES_DB_HOST,
    user: process.env.POSTGRES_DB_USER,
    password: process.env.POSTGRES_DB_PASSWORD,
    database: process.env.POSTGRES_DB_NAME,
    port: Number(process.env.POSTGRES_DB_PORT) || 5432,
});

const adapter = new PrismaPg(pool);
const prisma = new PrismaClient({ adapter });

export { prisma, adapter, PrismaClient };
