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

FROM cgr.dev/chainguard/laravel:latest
COPY --from=builder /app /app

WORKDIR /app

# Esponi la porta 8080 per FPM
EXPOSE 8080

# Avvia il server Laravel (non FPM che non serve HTTP direttamente)
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]