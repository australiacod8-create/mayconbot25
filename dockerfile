FROM php:8.2-apache

# Enable mod_rewrite
RUN a2enmod rewrite

# Install required extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Set file permissions for writable files
RUN chmod 777 users.json error.log
RUN chown -R www-data:www-data /var/www/html

# Expose port
EXPOSE 80