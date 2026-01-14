##FROM node:lts-alpine
# Set working directory
#WORKDIR /app
##WORKDIR /var/www/html/
# Copy package.json and package-lock.json first
##COPY package*.json ./
# Copy the rest of your code
##COPY . .
##COPY .npmrc .npmrc
# Install dependencies
# RUN npm install
##RUN npm install --legacy-peer-deps
# Build the app
##RUN npm run build
# Install 'serve' globally to serve the app
##RUN npm install -g serve
# Expose port 3000
##EXPOSE 3000
# Start the app
##CMD ["serve", "-s", "dist", "-l", "3000"]

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