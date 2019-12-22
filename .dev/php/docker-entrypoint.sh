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
