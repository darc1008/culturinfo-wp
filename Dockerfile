FROM wordpress:6.7-php8.3-apache

# Install wp-cli for seeding
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# Custom PHP limits
RUN echo 'memory_limit = 256M' > /usr/local/etc/php/conf.d/zz-wp-limits.ini \
    && echo 'upload_max_filesize = 64M' >> /usr/local/etc/php/conf.d/zz-wp-limits.ini \
    && echo 'post_max_size = 64M' >> /usr/local/etc/php/conf.d/zz-wp-limits.ini \
    && echo 'max_execution_time = 120' >> /usr/local/etc/php/conf.d/zz-wp-limits.ini

# Persistent seed script + sample articles
COPY seed/seed.sh /usr/local/bin/seed.sh
COPY seed/articles /seed/articles
RUN chmod +x /usr/local/bin/seed.sh

# Custom entrypoint that seeds then starts Apache
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

CMD ["/usr/local/bin/entrypoint.sh"]
