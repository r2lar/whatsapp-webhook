FROM php:8.2-apache

WORKDIR /var/www/html

COPY index.php /var/www/html/

RUN chown -R www-data:www-data /var/www/html \
    && a2enmod rewrite

EXPOSE 80

CMD ["apache2-foreground"]
