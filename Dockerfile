FROM php:8.4-bookworm AS builder

# Installa le dipendenze necessarie
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    supervisor 

# Configura e installa le estensioni PHP necessarie
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd pcntl bcmath zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Imposta la directory di lavoro
WORKDIR /app

# Copia i file dell'applicazione
COPY . .

# Installa le dipendenze del progetto
RUN composer install --no-dev --optimize-autoloader

FROM php:8.4-fpm-alpine
COPY --from=builder /app /app

# Installa nginx e supervisor
RUN apk add --no-cache nginx supervisor

WORKDIR /app

# Configura nginx
COPY ./nginx/default.conf /etc/nginx/nginx.conf

# Crea le directory necessarie per nginx
RUN mkdir -p /run/nginx /var/log/nginx

# Configura supervisord
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Esponi la porta 8080
EXPOSE 8080

# Avvia supervisord che gestirà nginx e php-fpm
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]