FROM php:8.2-cli

# Instala extensiones necesarias para Laravel
RUN apt-get update && apt-get install -y \
    git curl unzip zip libzip-dev libonig-dev libxml2-dev libpng-dev libonig-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring zip tokenizer xml

# Instala Composer desde imagen oficial
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# Define directorio de trabajo
WORKDIR /app

# Copia los archivos del proyecto
COPY . .

# Instala dependencias Laravel
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Permisos para carpetas requeridas
RUN chmod -R 775 storage bootstrap/cache

# Puerto expuesto por Laravel Artisan
EXPOSE 8080

# Comando de inicio del servidor embebido de Laravel
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
