FROM php:8.2-apache

# Enable mysqli extension for MySQL
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Enable Apache mod_rewrite (needed for clean URLs)
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy all project files into the container
COPY . /var/www/html/

# Allow .htaccess overrides
RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/allow-override.conf \
    && a2enconf allow-override

# Railway assigns a dynamic PORT — Apache must listen on it
RUN echo 'ServerName localhost' >> /etc/apache2/apache2.conf
CMD sed -i "s/80/${PORT:-80}/g" /etc/apache2/ports.conf \
    /etc/apache2/sites-enabled/000-default.conf \
    && apache2-foreground

EXPOSE 80
