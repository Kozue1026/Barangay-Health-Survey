# Option B: full web + /api/ on Koyeb/Render (mobile talks to /api/ -> same Firebase RTDB)
# Deploy with Root directory = Group-5-Enhance (where index.php lives).
FROM php:8.2-apache

# Apache + PHP extensions needed by config/firebase.php (curl, openssl, json, mbstring)
RUN a2enmod rewrite headers \
    && apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev libzip-dev unzip \
    && docker-php-ext-install -j$(nproc) opcache \
    && rm -rf /var/lib/apt/lists/*

# Allow .htaccess (config/.htaccess protects firebase_credentials.json, uploads/.htaccess blocks scripts)
RUN sed -i '/<Directory \/var\/www\/html>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

COPY . /var/www/html/

# Writable uploads for profile pics + fallback token cache (see config/firebase.php)
RUN mkdir -p /var/www/html/uploads/profile_pics \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 775 /var/www/html/uploads

# Koyeb/Render provide $PORT; Apache listens on 80 by default.
# If platform injects PORT, uncomment next lines to respect it:
# CMD sed -i "s/Listen 80/Listen ${PORT:-80}/" /etc/apache2/ports.conf && sed -i "s/:80>/:${PORT:-80}>/" /etc/apache2/sites-available/000-default.conf && apache2-foreground

EXPOSE 80
