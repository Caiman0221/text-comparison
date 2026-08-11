help:
	@echo "Доступные команды"
	@echo "    make start 			- собрать и поднять приложение"
	@echo "    make up				- поднять приложение"
	@echo "    make down 			- остановить приложение"
	@echo "    make nginx-logs 		- логи nginx"
	@echo "    make php-logs 		- логи php"
	@echo "    make help 			- а что тут делать? памагите"

start:
	@docker compose up -d --build --wait

up:
	@docker compose up -d

down:
	@docker compose down

nginx-logs:
	@docker logs nginx-server

php-logs:
	@docker logs php