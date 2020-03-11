# Anstehend
* rename process-owner -> process-manager in source + command + role
* add-user automatisch validiert erstellen
* testen: budget/min/max werden auf null gesetzt bei 0
* testen: autotrim?
* Fund nicht bearbeiten lassen wenn aktiv
* Doctrine\FundDeletedExtension? Können Funds gelöscht werden?
* Logo in der Mail austauschen
* onRefresh: Error: "Unable to load an user with property "username" = "jakob.schumann". If the user identity has changed, you must renew the token. Otherwise, verify that the "lexik_jwt_authentication.user_identity_field" config option is correctly set."
* Imprint/Region etc haben bei Fund/Process unterschiedliche max/min Längen? 280 Imprint Fund zu kurz?
* 100 Zeichen für ein Prozess-Kriterium zu kurz?
* password min length / strength
* localizedDate in der Email ist englisch, trotz locale:de in services.yaml?
* validate pw in pw reset listener + user DTO
* custom operations:
    * post POST listener: https://api-platform.com/docs/core/events/
    * prePersist EntityListener: https://symfonycasts.com/screencast/api-platform-security/entity-listener#codeblock-07a35d3111
* JuryCriterion & FundConcretization zu Subresource machen  
  https://api-platform.com/docs/core/subresources/
* API Annotations zu YAML migrieren statt bei den Entities
* db less user provider https://github.com/lexik/LexikJWTAuthenticationBundle/blob/master/Resources/doc/8-jwt-user-provider.md
* Entities
    * Unittests
        * Cron commands trigger cron events
        * Cron daily event dispatches PurgeValidationsMessage
        * uploadable
            * richtiger filename
            * richtiger dirname
            * entfernt beim Löschen
    * User Pre/Post Delete events
    * Filter implementieren
* ObjectRole API
* JuryRating API
* User-System
    * https://symfony.com/blog/new-in-symfony-4-4-password-migrations
* Namespace ändern
    * http://eresdev.com/how-to-change-namespace-of-symfony-4-project/
    * namespace for make konfigurieren
* ElasticSearch
    * manual indexer: https://symfony.com/doc/current/doctrine/events.html#doctrine-lifecycle-listeners
    * required features:
        * read/write (FOSElastica can r/w, Api Platform can only r)
        * support multiple indexes (one type each, forced for ES 7+)
        * search over multiple indexes (not included in FOSElastica)
        * support index aliases
        * support dev/test config to separate indexes
        * support index recreation for unit tests etc
        * map Doctrine entities/documents to ES, asynchronously via messenger
        * rebuild index via command
        * map ES results back to Doctrine?
    * use https://github.com/FriendsOfSymfony/FOSElasticaBundle or
      not supported / updated anymore?
        * FOSElastica setup: https://hugo-soltys.com/blog/autocomplete-search-with-elasticsearch-and-symfony-4
* PHP 7.4 preload
* Token refresh via refresh token stored in httponly samesite cookie 

# Postponed
* document which endpoints are public and which require authentication,
  currently swagger ui shows a lock on all actions
* enable Varnish http caching for better performance
* enable Mercure for pushed updates to watched documents/entities
* update ElasticSearch to 7.x+ including FosElasticaBundle,
  current FosElastica (5.1.1, 2019-10-04) only supports ES 5 & 6
* replace FosElastica Doctrine LifetimeEvents Listener with custom
  implementation which pushes the updates to the message queue,
  mimicking the behavior of enqueue/elastica-bundle (which we don't
  use as we use symfonys own message bus, @see https://github.com/FriendsOfSymfony/FOSElasticaBundle/blob/master/doc/cookbook/doctrine-queue-listener.md)
* enable versioning by adding an URL prefix like /api/v1
* rename /authentication_token path?
* use mongodb instead of SQL? https://www.doctrine-project.org/projects/doctrine-mongodb-odm/en/1.2/reference/best-practices.html#best-practices
* Email signing: https://symfony.com/blog/new-in-symfony-4-4-signing-and-encrypting-email-messages
* Trigger event when a Fund submission period start -> notification for PO and
  all projects?
* Notification X days before a Fund submission ends -> email to all projects
  that have an unsubmitted application for this fund
* Trigger event when a Fund submission period ends -> notification for PO

# Designentscheidungen
* Wir verwenden Symfony als Basis weil es seit einiger Zeit das populärste und
  am aktivsten weiterentwicklte Framework mit einer extrem großen Menge an
  Addons ist. ZendFramework ist seit einigen Jahren praktisch eingeschlafen.
* Wir verwenden API Platform weil wir hier (bspw. gegenüber FOSRestBundle) fast
  alle Features aus einer Hand bekommen (JSON-REST, Integration mit Doctrine,
  automatische API-Doku, JWT-Auth via Addon, ...)
* Vorerst verwenden wir nur MySQL/MariaDB um uns den Overhead zu sparen eine
  zweite Datenbank zu managen und zu testen (hautelook/alicebundle kann nicht
  ORM + ODM fixtures gleichzeitig managen, ODM fixtures werden von den
  Unittest-Traits nicht unterstützt, DAMATestFixtureBundle funktioniert nur via
  Transactions im ORM). Wir erwarten nicht extrem viele Daten (Millionen von
  Zeilen), auch keine hohe Anzahl von Schreibzugriffen und nutzen lieber die
  hohe Select-Performance der SQL-DBs.
  Um nicht übermässig viele Joins verwenden zu müssen werden dynamische Felder
  wie bspw. Angaben zum Förderantrag als JSON in einem Feld gespeichert, diese
  müssen auch nicht für die Suche indiziert  werden. Die API gibt sowieso immer 
  komplette Dokumente zurück, um die Verarbeitung der einzelnen Felder kümmert
  sich der Client. Wir schauen einmal wie weit
  wir damit kommen und können später noch MongoDB für solche Dokumente
  hinzufügen.
* Wir verwenden Next.js als Basis für den Client weil hier SSR und viele andere
  Features out-of-the-box dabei sind und die Entwicklung weiterhin aktiv ist.
  
# Bugreports & PRs
* Doctrine
    * Doctrine\DBAL\Platforms\MariaDb1027Platform should not be final,
      to allow extension, like the other classes 
    * Doctrine\DBAL\Driver\AbstractMySQLDriver getMariaDbMysqlVersionNumber and
      getOracleMysqlVersionNumber should not be private, to allow extension,
      when private we cannot createDatabasePlatformForVersion without
      re-implementing those two methods too
    * Doctrine\Common\DataFixtures\Purger\ORMPurger does not quote table names
      in purge() when using PURGE_MODE_DELETE, this causes errors with tables
      using keywords as name
    * Doctrine\Common\DataFixtures\Purger\ORMPurger does not seem to purge
      tables in the correct order (leaf tables first), it still causes foreign
      key errors:
      https://github.com/doctrine/DoctrineFixturesBundle/issues/50
      https://github.com/doctrine/data-fixtures/pull/127
* Api Platform
    * ApiPlatform\Core\Bridge\Doctrine\Common\DataPersister should not be final
      to allow extension, e.g. for encoding user password as shown in https://symfonycasts.com/screencast/api-platform-security/encode-user-password
    * For DTOs it should be possible to use PHP 7.4 typed properties without
      @var annotation to determine the type of Relations for Denormalization
* Symfony
    * Mailer: EsmtpTransport functions should be protected to allow inheritance,
      e.g. for testTransport that prevents usage of TLS/STARTTLS for local unittesting
      where the connection is intercepted by antivirus software and thus the certificate
      check fails, as there is no way to disable the check.
    * Mailer: CramMd5Authenticator only works with EsmtpTransport, uses
      executeCommand which isn't defined there but in SmtpTransport -> prevents
      re-use 
    * Messenger: https://github.com/symfony/symfony/issues/35129

# Multi-process platform ToDo
* DB
    * project name only unique per process
    * fund name only unique per process
* Filters
    * projects by process
    * funds by process
* Security
    * adapt Voters to check not for general ROLE_PROCESS_OWNER but check
      for privilege for the concrete process/fund/...
    * add new Voters for Process/Fund/FundConcretization 
* Users assigned to a process? Username unique for process? 