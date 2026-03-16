FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    && docker-php-ext-install mysqli \
    && docker-php-ext-enable mysqli \
    && a2enmod rewrite \
    && a2dismod mpm_event || true \
    && a2enmod mpm_prefork \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

COPY . /var/www/html/

RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

EXPOSE 8080

CMD bash -c "sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf && \
    sed -i 's/:80/:8080/' /etc/apache2/sites-enabled/000-default.conf && \
    apache2-foreground"
