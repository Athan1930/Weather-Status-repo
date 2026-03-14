FROM php:8.2-apache

RUN docker-php-ext-install mysqli \
    && docker-php-ext-enable mysqli \
    && a2enmod rewrite \
    && a2dismod mpm_event \
    && a2enmod mpm_prefork

RUN echo 'ServerName localhost' >> /etc/apache2/apache2.conf

RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

COPY . /var/www/html/

ENV PORT=80
CMD bash -c "sed -i 's/80/$PORT/g' /etc/apache2/ports.conf && sed -i 's/:80/:$PORT/g' /etc/apache2/sites-enabled/000-default.conf && apache2-foreground"

EXPOSE 80
