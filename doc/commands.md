# Installation / Configuration
* create messenger table
  `./bin/console messenger:setup-transports`
* create new (admin) user  
  `./bin/console app:add-user [username] [email] [password] [--admin]`
* create new process-manager  
  `./bin/console app:add-user [username] [email] [password] --process-owner`
* create keys for JWT auth  
  `openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096`
  `openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout`

# ElasticSearch handling
* view default template for new indices  
  `curl -X GET "localhost:9200/_template/default?pretty"`

* change the default index template  
  ```
     curl -X PUT "localhost:9200/_template/default?pretty" -H 'Content-Type: application/json' -d'
     {
       "template": ["*"],
       "index_patterns": "*",
       "order": -1,
       "settings": {
         "number_of_shards": "1",
         "number_of_replicas": "1"
       }
     }
     '
     ```
* list indices  
  `curl -X GET "localhost:9200/_cat/indices?v&pretty"`
* delete an index  
  `curl -X DELETE "localhost:9200/customer?pretty"`
* list of index aliases  
  `curl -X GET "http://localhost:9200/_alias/?pretty"`  
* sync indices with ORM/ODM db  
  `php bin/console fos:elastica:populate`

# Fixtures
* Datenbank befüllen (funktioniert nur in dev/test Umgebung)  
  Dev: `php bin/console doctrine:fixtures:load --group initial`  
  Test: `php bin/console -etest doctrine:fixtures:load --group test`
  
# Unittests
* Test-Suite ausführen (verwendet .env.test und überschreibt die Testdatenbank)  
  `php vendor/bin/simple-phpunit`
  
# Debugging
* Alle konfigurierten Services auflisten  
  `php bin/console debug:container`
* Standard-Config der Symfony-Pakete anzeigen  
  `php bin/console config:dump-reference [framework|debug|...]`
* Aktuelle eigene Config anzeigen  
  `php bin/console debug:config [framework|debug|...]`
