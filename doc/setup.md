# Installation & Setup

The application is designed to run within a docker container that provides
php-fpm. Use a reverse proxy like Nginx to serve any static files, add SSL and
define the URL the API runs on.
Requirements are a MariaDB/MySQL database (e.g. in separate container) and
a Redis container for caching.

## Create Docker container

Create a custom Dockerfile or use the three files below

* Dockerfile
```
FROM vrokdd/php:symfony

COPY ./crontab /etc/crontab

COPY ./docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint
ENTRYPOINT ["docker-entrypoint"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
```

* docker-entrypoint.sh
```
#!/bin/sh
set -e

if [ "$APP_ENV" != 'prod' ]; then
    jwt_passphrase=$(grep '^JWT_PASSPHRASE=' .env | cut -f 2 -d '=')
    if [ ! -f config/jwt/private.pem ] || ! echo "$jwt_passphrase" | openssl pkey -in config/jwt/private.pem -passin stdin -noout > /dev/null 2>&1; then
        echo "Generating public / private keys for JWT"
        mkdir -p config/jwt
        echo "$jwt_passphrase" | openssl genpkey -out config/jwt/private.pem -pass stdin -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
        echo "$jwt_passphrase" | openssl pkey -in config/jwt/private.pem -passin stdin -out config/jwt/public.pem -pubout
        chown -R www-data:www-data config/jwt
    fi
fi

echo "Waiting for db to be ready..."
until bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; do
    sleep 1
done

if [ "$APP_ENV" != 'prod' ]; then
    bin/console doctrine:schema:update --force --no-interaction
fi

exec docker-php-entrypoint "$@"
```

* crontab
```
1 0 * * * www-data /usr/local/bin/php /var/www/html/bin/console cron:daily
0 * * * * www-data /usr/local/bin/php /var/www/html/bin/console cron:hourly
```

Setup in docker-compose.yaml
```
 fcpapi:
    build: fcp-api
    restart: on-failure:5
    container_name: fcpapi
    cpu_shares: 512
    mem_limit: 1500m
    dns: 8.8.8.8
    networks:
      custom:
    security_opt:
      - apparmor:docker-default
    environment:
      - APP_ENV=prod
    volumes:
      - /var/www/your-vhost/application-dir:/var/www/html
      - /var/www/your-vhost/log:/var/www/log
    links:
      - your-db-container:mysql
  redisfcpapi:
    container_name: redisfcpai
    image: redis
    restart: on-failure:5
    network_mode: service:fcpapi
    cpu_shares: 128
    mem_limit: 256m
    read_only: true
    security_opt:
      - apparmor:docker-default
```

## Install Application
* create empty database & db-user 
* clone the repository to /var/www/your-vhost/application-dir
* create the .env.local
** configure the mailer + database connection
* for production: create the JWT keys (see commands.md)
** set the passphrase used in .env.local
* `composer install` (inside or outside of the container)
* inside the container: `./bin/console doctrine:schema:update --force` to
  create the tables
* inside the container: create the symfony messenger table (see commands.md)
* inside the container: create an admin user and a process-manager (see commands.md)

## Serve the application through an reverse proxy

Example vhost config for Nginx:
```
server {
    listen  80;
    listen  [::]:80;
    server_name  your-domain;
    return  301 https://your-domain$request_uri;
}
server {
    listen  443 ssl http2;
    listen  [::]:443 ssl http2;

    server_name  your-domain;
    root  /var/www/your-vhost/application-dir/public;

    ssl_certificate  /var/www/letsencrypt/certs/your-domain/fullchain.pem;
    ssl_certificate_key  /var/www/letsencrypt/certs/your-domain/privkey.pem;
    add_header  Strict-Transport-Security "max-age=315360000; includeSubdomains; preload;";

    error_log /var/www/your-vhost/log/error.log;
    access_log /var/www/your-vhost/log/access.log main;

    # for letsencrypt /.well-known/acme-challenge
    include /etc/nginx/global/letsencrypt.conf;

    location / {
        # try to serve file directly, fallback to index.php
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/.+\.php(/|$) {
        fastcgi_pass fcpapi:9000;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/www/html/public$fastcgi_script_name;

        fastcgi_connect_timeout  120;
        fastcgi_send_timeout  180;
        fastcgi_read_timeout  180;
    }
}
```
