FROM node:22-alpine AS development

# Install OCR and PDF utility packages
RUN apk add --no-cache poppler-utils tesseract-ocr tesseract-ocr-data-eng

#working directory inside the container
WORKDIR /app

#copy package.json and package-lock.json file into the app
COPY package*.json ./

#Install depencies
RUN npm i

#Copy rest project files
COPY . .

# Generate Prisma Client after everything is in place
#RUN npx prisma generate

# Expose for hot reload
EXPOSE 3000

#Run dev command accoring to package.json
CMD ["npm", "run", "start:dev"]
