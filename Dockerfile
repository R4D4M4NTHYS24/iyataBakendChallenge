# Dockerfile
FROM php:8.2-cli

# 1) Instala deps del sistema, extensiones PHP y certificados CA
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      git \
      curl \
      unzip \
      zip \
      libzip-dev \
      libonig-dev \
      libxml2-dev \
      ca-certificates \
 && update-ca-certificates \
 && docker-php-ext-install \
      pdo_mysql \
      mbstring \
      zip \
 && rm -rf /var/lib/apt/lists/*

# 2) Copia Composer desde la imagen oficial y permite plugins si corre como root
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_CAFILE=/etc/ssl/certs/ca-certificates.crt

# 3) Define el directorio de trabajo
WORKDIR /app

# 4) Copia todo el proyecto y instala dependencias
COPY . .
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# 5) Ajusta permisos (si usas Laravel u otro framework que requiera storage/cache)
RUN chmod -R 775 storage bootstrap/cache || true

# 6) Expone el puerto que usará Render
EXPOSE 8080

# 7) Comando de arranque. Ajusta según tu framework
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
