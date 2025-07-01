FROM node:20-alpine

WORKDIR /app

# Copy package.json and yarn.lock to leverage Docker cache
COPY package.json yarn.lock* .npmrc .yarnrc.yml ./

# Install dependencies
RUN yarn install

# Copy the rest of the application
COPY . .

# Build the application
RUN yarn build

# Expose the port
EXPOSE 3000

# Create runtime environment script - fixed version
RUN echo '#!/bin/sh' > /entrypoint.sh && \
    echo 'echo "window.RUNTIME_ENV = { BACKEND_API: \"$NUXT_PUBLIC_BACKEND_API\" };" > /app/.output/public/runtime-env.js' >> /entrypoint.sh && \
    echo 'exec "$@"' >> /entrypoint.sh && \
    chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]

# Start the application
CMD ["node", ".output/server/index.mjs"] 