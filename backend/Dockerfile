# Image backend Laravel dengan FrankenPHP sebagai server aplikasi.
# Tidak ada nilai rahasia di file ini: APP_KEY, DB_PASSWORD, dll. diberikan
# lewat environment variable saat container dijalankan.
FROM dunglas/frankenphp:1.9.1-php8.3.26-bookworm

# Ekstensi PHP yang dibutuhkan (OPcache dan pdo_sqlite sudah tersedia di image dasar).
RUN install-php-extensions pdo_mysql zip

COPY --from=composer:2.9.2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Salin composer.json dan composer.lock lebih dulu, lalu pasang dependency.
# Lapisan ini hanya dibangun ulang jika kedua file tersebut berubah,
# sehingga perubahan kode aplikasi tidak memicu composer install lagi.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts --no-autoloader

# Baru setelah itu salin kode aplikasi.
COPY . .
RUN composer dump-autoload --no-dev --optimize

COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint && chmod +x /usr/local/bin/entrypoint

ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_LOCALE=id \
    CACHE_STORE=file \
    LOG_CHANNEL=stdout \
    LOG_LEVEL=info

EXPOSE 80

ENTRYPOINT ["entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
