FROM node:22-alpine AS client-build
COPY package.json /app/package.json
WORKDIR /app/client
COPY client/package*.json ./
RUN npm ci
COPY client/ ./
RUN npm run build

FROM node:22-alpine AS api-runtime
ENV NODE_ENV=production
WORKDIR /app
COPY package.json ./package.json
COPY api/package*.json ./api/
RUN cd api && npm ci --omit=dev
COPY api/src ./api/src
COPY --from=client-build /app/client/dist/employee-dashboard/browser ./client/dist/employee-dashboard/browser
EXPOSE 4000
CMD ["node", "api/src/server.js"]
