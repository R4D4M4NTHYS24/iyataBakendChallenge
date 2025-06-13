# Dockerfile (sin extensión, así se llama el fichero)
FROM php:8.2-cli

# 1) Instala deps del sistema y extensiones PHP mínimas para Laravel
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      git curl unzip zip libzip-dev libonig-dev libxml2-dev \
 && docker-php-ext-install \
      pdo_mysql \
      mbstring \
      zip \
 && rm -rf /var/lib/apt/lists/*

# 2) Copia Composer desde la imagen oficial
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# 3) Define el directorio de trabajo
WORKDIR /app

# 4) Copia todo el proyecto y instala dependencias
COPY . .
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# 5) Da permisos a storage y cache
RUN chmod -R 775 storage bootstrap/cache

# 6) Expone el puerto 8080 (el que Render usará)
EXPOSE 8080

# 7) Arranca el servidor embebido de Laravel
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
