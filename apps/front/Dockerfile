# Build stage
FROM node:24 AS build-stage

WORKDIR /app

# Enable corepack for Yarn 4
RUN corepack enable

# Copy package files and yarn config template
COPY package.json yarn.lock .yarnrc.dist.yml ./

# Generate .yarnrc.yml from secrets and install dependencies
RUN --mount=type=secret,id=OWLINT_REGISTRY_URL \
    --mount=type=secret,id=OWLINT_DEPLOY_KEY \
    cp .yarnrc.dist.yml .yarnrc.yml && \
    sed -i "s|<OWLINT_REGISTRY_URL>|$(cat /run/secrets/OWLINT_REGISTRY_URL)|g" .yarnrc.yml && \
    sed -i "s|<OWLINT_DEPLOY_KEY>|$(cat /run/secrets/OWLINT_DEPLOY_KEY)|g" .yarnrc.yml && \
    yarn install --immutable && \
    cp .yarnrc.dist.yml .yarnrc.yml

# Copy project files
COPY . .

# Build the app
RUN yarn build-only

# Production stage
FROM nginx:1.29-alpine

# Copy built app to nginx
COPY --chown=nginx:nginx --from=build-stage /app/dist /usr/share/nginx/html

# Copy nginx configuration
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
