FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    apache2 \
    php8.1 \
    php8.1-mysqli \
    libapache2-mod-php8.1 \
    && a2enmod rewrite \
    && apt-get clean

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html/

CMD bash -c "sed -i \"s/80/${PORT:-80}/g\" /etc/apache2/ports.conf && \
    sed -i \"s/:80/:${PORT:-80}/g\" /etc/apache2/sites-enabled/000-default.conf && \
    apache2ctl -D FOREGROUND"

EXPOSE 80
