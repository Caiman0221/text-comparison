FROM php:8.4-fpm

# Установка системных зависимостей
RUN apt-get update && apt-get install -y \
    libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# Установка PHP расширений
RUN docker-php-ext-install -j$(nproc) \
    mbstring

# Настройка рабочей директории
WORKDIR /var/www/app

EXPOSE 9000

CMD ["php-fpm"]
