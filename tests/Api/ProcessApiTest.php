<?php
declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\Process;
use App\Entity\UserObjectRole;
use App\PHPUnit\AuthenticatedClientTrait;
use App\PHPUnit\RefreshDatabaseTrait;

/**
 * @group ProcessApi
 */
class ProcessApiTest extends ApiTestCase
{
    use AuthenticatedClientTrait;
    use RefreshDatabaseTrait;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    public function testGetCollection(): void
    {
        $response = static::createClient()
            ->request('GET', '/processes');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceCollectionJsonSchema(Process::class);

        self::assertJsonContains([
            '@context'         => '/contexts/Process',
            '@id'              => '/processes',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
        ]);

        $collection = $response->toArray();

        $this->assertCount(1, $collection['hydra:member']);
    }

    public function testGetProcess(): void
    {
        $client = static::createClient();

        $iri = $this->findIriBy(Process::class, ['id' => 1]);

        $response = $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(Process::class);

        self::assertJsonContains([
            '@id'         => $iri,
            'description' => 'Description for Test-Process',
            'id'          => 1,
            'imprint'     => 'FCP Test',
            'name'        => 'Test-Process äüöß',
            'region'      => 'Dresden',
            'goals'     => ['first goal', 'second goal'],
            'criteria'    => null,
        ]);

        // $processData = $response->toArray();
        // @todo logo, funds, projects prüfen
    }

    public function testCreateProcess(): void
    {
        $response = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ])->request('POST', '/processes', ['json' => [
            'description' => 'just for fun',
            'imprint'     => 'The Testers',
            'name'        => 'Masterprocess',
            'region'      => 'Berlin',
            'goals'     => ['no goal'],
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(Process::class);

        self::assertJsonContains([
            '@context'    => '/contexts/Process',
            '@type'       => 'Process',
            'criteria'    => null,
            'description' => 'just for fun',
            'imprint'     => 'The Testers',
            'name'        => 'Masterprocess',
            'region'      => 'Berlin',
            'goals'     => ['no goal'],
        ]);

        $processData = $response->toArray();
        $this->assertRegExp('~^/processes/\d+$~', $processData['@id']);
        $this->assertArrayHasKey('id', $processData);
        $this->assertIsInt($processData['id']);

        // @todo logo, funds, projects prüfen
    }

    public function testCreateFailsUnauthenticated(): void
    {
        static::createClient()->request('POST', '/processes', ['json' => [
            'description' => 'just for fun',
            'imprint'     => 'The Testers',
            'name'        => 'Masterprocess',
            'region'      => 'Berlin',
            'goals'     => ['no goal'],
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testCreateFailsWithoutPrivilege(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ])->request('POST', '/users', ['json' => [
            'description' => 'just for fun',
            'imprint'     => 'The Testers',
            'name'        => 'Masterprocess',
            'region'      => 'Berlin',
            'goals'     => ['none'],
        ]]);

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Access Denied.',
        ]);
    }

    public function testCreateWithoutNameFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ])->request('POST', '/processes', ['json' => [
            'description' => 'just for fun',
            'imprint'     => 'The Testers',
            'region'      => 'Berlin',
            'goals'     => ['no goal'],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'name: This value should not be blank.',
        ]);
    }

    public function testCreateWithDuplicateNameFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ])->request('POST', '/processes', ['json' => [
            'description' => 'just for fun',
            'imprint'     => 'The Testers',
            'name'        => 'Test-Process äüöß',
            'region'      => 'Berlin',
            'goals'     => ['no goal'],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'name: Name already exists.',
        ]);
    }

    public function testCreateWithInvalidGoalsFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ])->request('POST', '/processes', ['json' => [
            'description' => 'just for fun',
            'imprint'     => 'The Testers',
            'region'      => 'Berlin',
            'goals'     => 'none', // should be an array to work
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'The type of the "goals" attribute must be "array", "string" given.',
        ]);
    }

    public function testCreateWithEmptyGoalsFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ])->request('POST', '/processes', ['json' => [
            'criteria'    => null,
            'description' => 'just for fun',
            'imprint'     => 'The Testers',
            'name'        => 'Another test',
            'region'      => 'Berlin',
            'goals'     => [], // should be not empty to work
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'goals: validate.general.notBlank',
            'violations' => [
                [
                    'propertyPath' => 'goals',
                    'message'      => 'validate.general.notBlank'
                ]
            ],
        ]);
    }

    public function testCreateWithEmptyGoalFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ])->request('POST', '/processes', ['json' => [
            'criteria'    => null,
            'description' => 'just for fun',
            'imprint'     => 'The Testers',
            'name'        => 'Another test',
            'region'      => 'Berlin',
            'goals'     => [null], // minlength = 5
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'goals[0]: validate.general.notBlank',
            'violations' => [
                [
                    'propertyPath' => 'goals[0]',
                    'message'      => 'validate.general.notBlank'
                ]
            ],
        ]);
    }

    public function testCreateWithShortGoalFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ])->request('POST', '/processes', ['json' => [
            'criteria'    => null,
            'description' => 'just for fun',
            'imprint'     => 'The Testers',
            'name'        => 'Another test',
            'region'      => 'Berlin',
            'goals'     => ['123'], // minlength = 5
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'goals[0]: validate.general.tooShort',
            'violations' => [
                [
                    'propertyPath' => 'goals[0]',
                    'message'      => 'validate.general.tooShort'
                ]
            ],
        ]);
    }

    public function testUpdateProcess(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(Process::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'description' => 'just for work',
            'imprint'     => 'The Processor',
            'name'        => 'Test-Process #2',
            'region'      => 'Paris',
            'goals'       => ['some goal', 'others'],
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'         => $iri,
            'description' => 'just for work',
            'imprint'     => 'The Processor',
            'name'        => 'Test-Process #2',
            'region'      => 'Paris',
            'goals'       => ['some goal', 'others'],
        ]);
    }

    public function testUpdateFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(Process::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'description' => 'just for work',
            'imprint'     => 'The Processor',
            'name'        => 'Test-Process #2',
            'region'      => 'Paris',
            'goals'       => ['some goal', 'others'],
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testUpdateFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $iri = $this->findIriBy(Process::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'description' => 'Test-Process äüöß',
            'imprint'     => 'The Processor',
            'name'        => 'Test-Process #2',
            'region'      => 'Paris',
            'goals'       => ['some goal', 'others'],
        ]]);

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Access Denied.',
        ]);
    }

    public function testUpdateWithDuplicateNameFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        // add a second process to the db, we will try to name it like the first
        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $process = new Process();
        $process->setName('just for work');
        $process->setDescription('Description for Test-Process');
        $process->setGoals(['first goal', 'second goal']);
        $process->setRegion('Dresden');
        $process->setImprint('FCP Test');
        $em->persist($process);
        $em->flush();

        $iri = $this->findIriBy(Process::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'description' => 'something with 20 characters',
            'imprint'     => 'The Processor',
            'name'        => 'Test-Process äüöß',
            'region'      => 'Paris',
            'goals'     => ['some goals', 'others'],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'name: Name already exists.',
        ]);
    }

    public function testUpdateWithEmptyNameFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(Process::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'description' => 'something with 20 characters',
            'imprint'     => 'The Processor',
            'name'        => '',
            'region'      => 'Paris',
            'goals'       => ['some goals', 'others'],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'name: This value should not be blank.',
        ]);
    }

    public function testUpdateOfIdIsIgnoredProcess(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(Process::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'id' => '33',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'         => $iri,
            'id'          => 1,
            'name'        => 'Test-Process äüöß',
            'slug'        => 'test-process-auoss',
        ]);
    }

    public function testUpdateOfSlugIsIgnoredProcess(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(Process::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'slug'        => 'will-not-work',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'         => $iri,
            'id'          => 1,
            'name'        => 'Test-Process äüöß',
            'slug'        => 'test-process-auoss',
        ]);
    }

    public function testDelete(): void
    {
        $before = $this->entityManager->getRepository(UserObjectRole::class)
            ->findBy(['objectId' => 1, 'objectType' => Process::class]);
        $this->assertCount(1, $before);

        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $iri = $this->findIriBy(Process::class, ['id' => 1]);
        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        $deleted = static::$container->get('doctrine')
            ->getRepository(Process::class)
            ->find(1);
        $this->assertNull($deleted);

        $after = $this->entityManager->getRepository(UserObjectRole::class)
            ->findBy(['objectId' => 1, 'objectType' => Process::class]);
        $this->assertCount(0, $after);
    }

    public function testDeleteFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(Process::class, ['id' => 1]);
        $client->request('DELETE', $iri);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testDeleteFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Process::class, ['id' => 1]);
        $client->request('DELETE', $iri);

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Access Denied.',
        ]);
    }

    /**
     * Test that the DELETE operation for the whole collection is not available.
     */
    public function testCollectionDeleteNotAvailable(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ])->request('DELETE', '/processes');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "DELETE /processes": Method Not Allowed (Allow: GET, POST)',
        ]);
    }
}
