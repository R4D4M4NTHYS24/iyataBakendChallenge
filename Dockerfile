# Usa PHP con servidor embebido
FROM php:8.2-cli

# Instala extensiones necesarias
RUN apt-get update && apt-get install -y \
    git curl unzip zip libzip-dev libonig-dev && \
    docker-php-ext-install pdo mbstring zip

# Instala Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# Define la raíz de trabajo
WORKDIR /app

# Copia los archivos del proyecto
COPY . .

# Instala dependencias Laravel
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Asigna permisos a storage y bootstrap/cache
RUN chmod -R 775 storage bootstrap/cache

# Expone el puerto 8080 que Render espera
EXPOSE 8080

# Instrucción de arranque
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
