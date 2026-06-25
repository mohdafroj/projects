FROM node:20.18.2-alpine AS deps
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm install --include=dev

FROM node:20.18.2-alpine AS build
WORKDIR /app
COPY --from=deps /app/node_modules ./node_modules
COPY . .
RUN npm run build

FROM node:20.18.2-alpine
WORKDIR /app
ENV NODE_ENV=production
COPY --chown=node:node package.json package-lock.json* ./
RUN npm ci --omit=dev && npm cache clean --force
COPY --chown=node:node --from=build /app/dist ./dist
RUN chown -R node:node /app
USER node
EXPOSE 1234
CMD ["node", "dist/server.js"]
