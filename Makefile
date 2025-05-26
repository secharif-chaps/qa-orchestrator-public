.PHONY: build up down logs restart clean ps

# Default target
all: up

# Build all containers
build:
	docker-compose build

# Start all containers
up:
	docker-compose up -d

# Stop all containers
down:
	docker-compose down

# View logs from all containers
logs:
	docker-compose logs -f

# Restart all containers
restart: down up

# Remove containers, volumes, and images
clean:
	docker-compose down -v --rmi all

# Show running containers
ps:
	docker-compose ps 