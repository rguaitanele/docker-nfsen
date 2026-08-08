build:
	docker compose build

up:
	docker compose up -d

logs:
	docker compose logs -f nfsen

down:
	docker compose down

.PHONY: build up logs down
