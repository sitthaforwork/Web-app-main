FROM php:8.2-apache

# Enable mod_rewrite and PDO MySQL
RUN a2enmod rewrite
RUN docker-php-ext-install pdo pdo_mysql

# Enable .htaccess overrides and configure VirtualHost for any port
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
RUN sed -i 's/<VirtualHost \*:80>/<VirtualHost *:*>/' /etc/apache2/sites-available/000-default.conf

# Copy application files
COPY . /var/www/html/

# Copy and set entrypoint script
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

CMD ["/usr/local/bin/entrypoint.sh"]
