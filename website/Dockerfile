FROM php:8.3-fpm-alpine

RUN docker-php-ext-install pdo_mysql \
    && addgroup -S appgroup \
    && adduser -S -D -H -G appgroup appuser

WORKDIR /var/www/html
COPY --chown=appuser:appgroup public/ /var/www/html/
USER appuser

EXPOSE 9000
CMD ["php-fpm", "-F"]
