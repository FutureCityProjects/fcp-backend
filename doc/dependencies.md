# Used dependencies
* friendsofsymfony/elastica-bundle - index our ORM entities and ODM documents
  automatically in ElasticSearch for fast searching
* lexik/jwt-authentication-bundle - JWT generation & handling for api platform

## Development-only dependencies
* [alice](https://github.com/nelmio/alice) (hautelook/alice-bundle) - generating
  test fixtures
* [test-pack][symfony/test-pack] +  [http-client][symfony/http-client] +
  justinrainbow/json-schema - testing
* php-unit/php-unit - to enforce a newer version as the test-pack installs 7.x,
  api-platform classes require 8.x
