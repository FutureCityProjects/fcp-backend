# Used dependencies
* antishov/doctrine-extensions-bundle - automatically manage
  slugs, creation date etc for our entities, use instead of
  stof/doctrine-extensions-bundle for Symfony 4+ compatibility
* lexik/jwt-authentication-bundle - JWT generation & handling for api platform
* tuupola/base62 - to generate tokens with [A-Za-z0-9] instead of hex chars to
  reduce URL length
* twig/extensions - for localizedDate in templates etc.
* vich/uploader-bundle - managing file meta data in the DB &
  files on disk

## Development-only dependencies
* doctrine/doctrine-fixtures-bundle - regenerating test database
* [test-pack][symfony/test-pack] +  [http-client][symfony/http-client] +
  justinrainbow/json-schema - testing
* php-unit/php-unit - to enforce a newer version as the test-pack installs 7.x,
  api-platform classes require 8.x
* zalas/phpunit-globals - to change environment variables for specific test