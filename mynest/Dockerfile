# ============ BASE ============
FROM node:22-alpine AS base
WORKDIR /app
COPY package*.json tsconfig*.json nest-cli.json ./
RUN npm ci

# ============ DEVELOPMENT ============
FROM base AS development
ENV NODE_ENV=development
COPY . .
EXPOSE 3000
CMD ["npm", "run", "start:dev"]

# ============ BUILD ============
FROM base AS build
ENV NODE_ENV=production
COPY . .
RUN npm run build

# ============ STAGING ============
FROM node:22-alpine AS staging
WORKDIR /app
ENV NODE_ENV=production

# Install only production deps
COPY package*.json ./
RUN npm ci --omit=dev && npm cache clean --force

# Copy built artifacts from build stage
COPY --from=build /app/dist ./dist
COPY --from=build /app/node_modules/.prisma ./node_modules/.prisma
COPY --from=build /app/package.json ./package.json

# Optional: Copy config, env files, etc.
# COPY config/ ./config/

EXPOSE 3000
CMD ["node", "dist/main.js"]

# ============ PRODUCTION ============
FROM node:22-alpine AS production
WORKDIR /app
ENV NODE_ENV=production

# Create non-root user
RUN addgroup -g 1001 -S nodejs
RUN adduser -S nestjs -u 1001

# Copy only necessary files
COPY --from=staging --chown=nestjs:nodejs /app/package.json ./
COPY --from=staging --chown=nestjs:nodejs /app/node_modules ./node_modules
COPY --from=staging --chown=nestjs:nodejs /app/dist ./dist

# Switch to non-root user
USER nestjs

EXPOSE 3000

CMD ["node", "dist/main.js"]