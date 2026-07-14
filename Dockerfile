FROM wordpress:6.7-php8.3-apache

# Install MariaDB server + supervisord + wp-cli
RUN apt-get update && apt-get install -y --no-install-recommends \
    mariadb-server \
    supervisor \
    curl \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/* \
    && curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# PHP limits
RUN echo 'memory_limit = 256M' > /usr/local/etc/php/conf.d/zz-wp-limits.ini \
    && echo 'upload_max_filesize = 64M' >> /usr/local/etc/php/conf.d/zz-wp-limits.ini \
    && echo 'post_max_size = 64M' >> /usr/local/etc/php/conf.d/zz-wp-limits.ini \
    && echo 'max_execution_time = 120' >> /usr/local/etc/php/conf.d/zz-wp-limits.ini

# MariaDB runtime config
RUN mkdir -p /var/run/mysqld /var/lib/mysql \
    && chown -R mysql:mysql /var/run/mysqld /var/lib/mysql
COPY mariadb.cnf /etc/mysql/conf.d/culturinfo.cnf

# supervisord to run mariadb + apache together
COPY supervisord.conf /etc/supervisor/conf.d/culturinfo.conf

# Seed scripts and sample articles
COPY seed/seed.sh /usr/local/bin/seed.sh
COPY seed/articles /seed/articles
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/seed.sh /usr/local/bin/entrypoint.sh

# Copy WordPress core into a separate directory (not /var/www/html which is a volume mount)
# We'll point Apache at /wp-src for first run, then move to /var/www/html after init
# Simpler: just unpack wp core into image so it's available even with volume mount on first start
RUN echo "WordPress source: $(ls /var/www/html | wc -l) files"

# WordPress gets installed on first start; entrypoint initializes DB if empty
ENV APACHE_RUN_USER=www-data
ENV APACHE_RUN_GROUP=www-data
ENV MARIADB_DATABASE=culturinfo
ENV MARIADB_USER=culturinfo
ENV MARIADB_PASSWORD=Cult1nf0_M4r1adb_2026!
ENV MARIADB_ROOT_PASSWORD=Cult1nf0_R00t_2026!

EXPOSE 80

CMD ["/usr/local/bin/entrypoint.sh"]
