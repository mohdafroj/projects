# --- Stage 1: Build ---
FROM node:lts-alpine AS builder
WORKDIR /app
COPY package*.json ./
COPY .npmrc .npmrc
RUN npm install
COPY . .
RUN npm run build
# --- Stage 2: Production ---
FROM node:lts-alpine
WORKDIR /app
# Install serve to serve static files
RUN npm install -g serve
# Copy only the built artifacts from the builder stage
COPY --from=builder /app/dist ./dist
EXPOSE 3000
CMD ["serve", "-s", "dist", "-l", "3000"]