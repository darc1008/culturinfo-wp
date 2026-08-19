FROM wordpress:7.0.2-php8.3-apache AS core-package

RUN apt-get update && apt-get install -y --no-install-recommends zip \
    && mkdir -p /tmp/culturinfo-core/wordpress /opt/culturinfo/core \
    && find /usr/src/wordpress -mindepth 1 -maxdepth 1 ! -name wp-content \
        -exec cp -a {} /tmp/culturinfo-core/wordpress/ \; \
    && cd /tmp/culturinfo-core \
    && zip -qr /opt/culturinfo/core/wordpress-7.0.2-no-content.zip wordpress

FROM wordpress:7.0.2-php8.3-apache

# Install MariaDB, audio runtime and wp-cli
RUN apt-get update && apt-get install -y --no-install-recommends \
    mariadb-server \
    supervisor \
    curl \
    ca-certificates \
    python3 \
    python3-venv \
    lame \
    libgomp1 \
    tzdata \
    util-linux \
    && rm -rf /var/lib/apt/lists/* \
    && curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# Local neural text-to-speech. The engine and voice are pinned so production
# does not change pronunciation unexpectedly between deployments.
RUN python3 -m venv /opt/culturinfo/piper \
    && /opt/culturinfo/piper/bin/pip install --no-cache-dir piper-tts==1.7.0 \
    && mkdir -p /opt/culturinfo/voices \
    && curl -fL 'https://huggingface.co/rhasspy/piper-voices/resolve/v1.0.0/es/es_MX/claude/high/es_MX-claude-high.onnx' -o /opt/culturinfo/voices/es_MX-claude-high.onnx \
    && curl -fL 'https://huggingface.co/rhasspy/piper-voices/resolve/v1.0.0/es/es_MX/claude/high/es_MX-claude-high.onnx.json' -o /opt/culturinfo/voices/es_MX-claude-high.onnx.json \
    && curl -fsSL 'https://www.apache.org/licenses/LICENSE-2.0.txt' -o /opt/culturinfo/voices/LICENSE-APACHE-2.0.txt
COPY licenses/es_MX-claude-high.MODEL_CARD.md /opt/culturinfo/voices/MODEL_CARD.md

# PHP limits
RUN echo 'memory_limit = 256M' > /usr/local/etc/php/conf.d/zz-wp-limits.ini \
    && echo 'upload_max_filesize = 64M' >> /usr/local/etc/php/conf.d/zz-wp-limits.ini \
    && echo 'post_max_size = 64M' >> /usr/local/etc/php/conf.d/zz-wp-limits.ini \
    && echo 'max_execution_time = 120' >> /usr/local/etc/php/conf.d/zz-wp-limits.ini

# MariaDB runtime config
RUN mkdir -p /var/run/mysqld /var/lib/mysql \
    && chown -R mysql:mysql /var/run/mysqld /var/lib/mysql
COPY mariadb.cnf /etc/mysql/conf.d/culturinfo.cnf

# Uploads is writable at runtime, so Apache must never execute code from it.
COPY apache/culturinfo-uploads.conf /etc/apache2/conf-available/culturinfo-uploads.conf
RUN a2enmod headers && a2enconf culturinfo-uploads

# supervisord to run mariadb + apache together
COPY supervisord.conf /etc/supervisor/conf.d/culturinfo.conf

# Seed scripts and sample articles
COPY seed/seed.sh /usr/local/bin/seed.sh
COPY seed/articles /seed/articles
COPY seed/assign_menu.php /seed/assign_menu.php
COPY seed/configure_menu.php /seed/configure_menu.php
COPY seed/configure_proxy.php /seed/configure_proxy.php
COPY seed/configure_security.php /seed/configure_security.php
COPY seed/configure_rank_math.php /seed/configure_rank_math.php
COPY seed/configure_contact.php /seed/configure_contact.php
COPY wp-content/themes/culturinfo /opt/culturinfo/theme
COPY wp-content/plugins/culturinfo-ads /opt/culturinfo/plugins/culturinfo-ads
COPY wp-content/plugins/culturinfo-authors /opt/culturinfo/plugins/culturinfo-authors
COPY wp-content/plugins/culturinfo-stats /opt/culturinfo/plugins/culturinfo-stats
COPY wp-content/plugins/culturinfo-publishing /opt/culturinfo/plugins/culturinfo-publishing
COPY wp-content/plugins/culturinfo-contact /opt/culturinfo/plugins/culturinfo-contact
COPY wp-content/plugins/culturinfo-audio /opt/culturinfo/plugins/culturinfo-audio
COPY wp-content/plugins/culturinfo-security /opt/culturinfo/plugins/culturinfo-security
COPY --from=core-package /opt/culturinfo/core/wordpress-7.0.2-no-content.zip /opt/culturinfo/core/wordpress-7.0.2-no-content.zip
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
COPY audio-worker.sh /usr/local/bin/audio-worker.sh
COPY culturinfo-backup.sh /usr/local/bin/culturinfo-backup.sh
COPY backup-worker.sh /usr/local/bin/backup-worker.sh
RUN mkdir -p /backups \
    && chmod +x /usr/local/bin/seed.sh \
        /usr/local/bin/entrypoint.sh \
        /usr/local/bin/audio-worker.sh \
        /usr/local/bin/culturinfo-backup.sh \
        /usr/local/bin/backup-worker.sh

# Copy WordPress core into a separate directory (not /var/www/html which is a volume mount)
# We'll point Apache at /wp-src for first run, then move to /var/www/html after init
# Simpler: just unpack wp core into image so it's available even with volume mount on first start
RUN echo "WordPress source: $(ls /var/www/html | wc -l) files"

# WordPress gets installed on first start; entrypoint initializes DB if empty
ENV APACHE_RUN_USER=www-data
ENV APACHE_RUN_GROUP=www-data
ENV MARIADB_DATABASE=culturinfo
ENV MARIADB_USER=culturinfo
ENV CULTURINFO_PIPER_PYTHON=/opt/culturinfo/piper/bin/python
ENV CULTURINFO_PIPER_MODEL=/opt/culturinfo/voices/es_MX-claude-high.onnx
ENV CULTURINFO_AUDIO_WORKER_INTERVAL=15
ENV CULTURINFO_BACKUPS_ENABLED=false
ENV CULTURINFO_BACKUP_HOUR=3
ENV CULTURINFO_BACKUP_RETENTION_DAYS=30

EXPOSE 80

CMD ["/usr/local/bin/entrypoint.sh"]
