# Build stage
FROM node:22.20.0-alpine AS build-stage

WORKDIR /app

# Copy package files and npm config
COPY package*.json ./
COPY .npmrc ./

# Install dependencies
RUN npm install

# Copy project files
COPY . .

# Build the app
RUN npm run build-only

# Production stage
FROM nginx:1.29

# Copy built app to nginx
COPY --chown=nginx:nginx --from=build-stage /app/dist /usr/share/nginx/html

# Copy nginx configuration
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
