FROM php:8.3-apache
RUN apt-get update \
    && apt-get install -y --no-install-recommends libxml2-dev libcurl4-openssl-dev \
    && docker-php-ext-install pdo_sqlite sqlite3 dom simplexml curl \
    && rm -rf /var/lib/apt/lists/*
COPY . /var/www/html/
COPY security/apache-security.conf /etc/apache2/conf-enabled/market-security.conf
RUN mkdir -p /var/www/html/uploads/produce && chown -R www-data:www-data /var/www/html/uploads
ENV PORT=10000
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf && sed -i 's/:80/:${PORT}/' /etc/apache2/sites-available/000-default.conf
EXPOSE 10000
