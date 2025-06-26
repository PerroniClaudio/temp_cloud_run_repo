# Usa l'immagine ufficiale di PHP 8.3 con FPM (Laravel 12 richiede PHP 8.2+)
FROM php:8.3-fpm

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
    supervisor \
    

# Configura e installa le estensioni PHP necessarie
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd pcntl bcmath zip

# Installa l'estensione Redis
RUN pecl install redis && docker-php-ext-enable redis

# Imposta la max filesize a 20MB su php.ini
RUN echo "upload_max_filesize = 20M" >> /usr/local/etc/php/php.ini
RUN echo "post_max_size = 20M" >> /usr/local/etc/php/php.ini



# Installa Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Imposta la directory di lavoro
WORKDIR /var/www/html

# Copia i file dell'applicazione
COPY . .



# Installa le dipendenze del progetto
RUN composer install

# Forza php-fpm ad ascoltare su 0.0.0.0:8080
RUN sed -i 's|^listen = .*|listen = 0.0.0.0:8080|' /usr/local/etc/php-fpm.d/www.conf

# Se il comando precedente fallisce, puoi decommentare la seguente riga per ignorare l'errore
# RUN echo "Composer install failed, but continuing anyway"

# Ottimizza la configurazione per la produzione
# RUN php artisan config:cache \
#     && php artisan route:cache \
#     && php artisan view:cache

# Imposta i permessi corretti
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage

# Crea un link simbolico per vedere i log Laravel in stdout
RUN mkdir -p /var/www/html/storage/logs && \
    touch /var/www/html/storage/logs/laravel.log && \
    ln -sf /dev/stdout /var/www/html/storage/logs/laravel.log

# Esponi la porta 8080 per FPM
EXPOSE 8080

# Avvia il server Laravel (non FPM che non serve HTTP direttamente)
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
