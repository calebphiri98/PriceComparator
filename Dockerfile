FROM php:8.3-apache

RUN printf '%s\n' \
    'file_uploads=On' \
    'upload_max_filesize=5M' \
    'post_max_size=12M' \
    'max_file_uploads=10' \
    > /usr/local/etc/php/conf.d/99-agri-uploads.ini


RUN apt-get update && \
    apt-get install -y --no-install-recommends libxml2-dev libcurl4-openssl-dev libsqlite3-dev libpq-dev && \
    docker-php-ext-install pdo_pgsql pgsql && \
    php -r "if (!in_array('pgsql', PDO::getAvailableDrivers(), true)) { exit(1); }" && \
    rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/

RUN printf '%s\n' \
    '<Directory /var/www/html>' \
    'Options -Indexes' \
    'AllowOverride All' \
    '</Directory>' \
    '<Directory /var/www/html/includes>' \
    'Require all denied' \
    '</Directory>' \
    '<FilesMatch "(^\.|config\.php$|setup-admin\.php$|sync-market\.php$|diagnose-market\.php$|check-db\.php$|check-sms-log\.php$|database\.sqlite.*|\.(sqlite|db|sql|env|log|bak|zip)$)">' \
    'Require all denied' \
    '</FilesMatch>' \
    > /etc/apache2/conf-enabled/agri-security.conf

RUN mkdir -p /var/www/html/uploads/produce && \
    chown -R www-data:www-data /var/www/html

ENV PORT=10000

RUN sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf && \
    sed -i 's/:80/:10000/' /etc/apache2/sites-available/000-default.conf

EXPOSE 10000
CMD ["sh", "-c", "test -f /etc/secrets/config.php || { echo 'Missing Render config.php secret'; exit 1; }; cp /etc/secrets/config.php /var/www/html/config.php && sed -i '1s/^\\xEF\\xBB\\xBF//' /var/www/html/config.php && chown www-data:www-data /var/www/html/config.php && chmod 640 /var/www/html/config.php && exec apache2-foreground"]
