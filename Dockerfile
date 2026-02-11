# Build stage
FROM node:22.20.0-alpine AS build-stage

WORKDIR /app

# Enable corepack for Yarn 4
RUN corepack enable

# Copy package files and yarn config
COPY package.json yarn.lock .yarnrc.yml ./

# Install dependencies
RUN yarn install --immutable

# Copy project files
COPY . .

# Build the app
RUN yarn build-only

# Production stage
FROM nginx:1.29

# Copy built app to nginx
COPY --chown=nginx:nginx --from=build-stage /app/dist /usr/share/nginx/html

# Copy nginx configuration
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
