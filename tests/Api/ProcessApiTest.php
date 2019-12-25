<?php
declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\Process;
use App\PHPUnit\AuthenticatedClientTrait;
use App\PHPUnit\RefreshDatabaseTrait;

/**
 * @group ProcessApi
 */
class ProcessApiTest extends ApiTestCase
{
    use AuthenticatedClientTrait;
    use RefreshDatabaseTrait;

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
            'targets'     => ['first target', 'second target'],
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
            'targets'     => ['no target'],
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
            'targets'     => ['no target'],
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
            'targets'     => ['no target'],
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
            'targets'     => ['none'],
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
            'targets'     => ['no target'],
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
            'targets'     => ['no target'],
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

    public function testCreateWithInvalidTargetsFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ])->request('POST', '/processes', ['json' => [
            'description' => 'just for fun',
            'imprint'     => 'The Testers',
            'region'      => 'Berlin',
            'targets'     => 'none', // should be an array to work
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'The type of the "targets" attribute must be "array", "string" given.',
        ]);
    }

    public function testCreateWithEmptyTargetsFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ])->request('POST', '/processes', ['json' => [
            'criteria'    => null,
            'description' => 'just for fun',
            'imprint'     => 'The Testers',
            'name'        => 'Another test',
            'region'      => 'Berlin',
            'targets'     => [], // should be not empty to work
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'targets: This value should not be blank.',
            'violations' => [
                [
                    'propertyPath' => 'targets',
                    'message'      => 'This value should not be blank.'
                ]
            ],
        ]);
    }

    public function testCreateWithEmptyTargetFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ])->request('POST', '/processes', ['json' => [
            'criteria'    => null,
            'description' => 'just for fun',
            'imprint'     => 'The Testers',
            'name'        => 'Another test',
            'region'      => 'Berlin',
            'targets'     => [null], // minlength = 5
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'targets[0]: This value should not be blank.',
            'violations' => [
                [
                    'propertyPath' => 'targets[0]',
                    'message'      => 'This value should not be blank.'
                ]
            ],
        ]);
    }

    public function testCreateWithShortTargetFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ])->request('POST', '/processes', ['json' => [
            'criteria'    => null,
            'description' => 'just for fun',
            'imprint'     => 'The Testers',
            'name'        => 'Another test',
            'region'      => 'Berlin',
            'targets'     => ['123'], // minlength = 5
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'targets[0]: This value is too short.',
            'violations' => [
                [
                    'propertyPath' => 'targets[0]',
                    'message'      => 'This value is too short.'
                ]
            ],
        ]);
    }

    public function testCreateWithEmptyCriteriaFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ])->request('POST', '/processes', ['json' => [
            'criteria'    => [], // should be null to work
            'description' => 'just for fun',
            'imprint'     => 'The Testers',
            'name'        => 'Another test',
            'region'      => 'Berlin',
            'targets'     => ['single target'],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'criteria: This value should not be blank.',
            'violations' => [
                [
                    'propertyPath' => 'criteria',
                    'message'      => 'This value should not be blank.'
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
            'targets'     => ['some target', 'others'],
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'         => $iri,
            'description' => 'just for work',
            'imprint'     => 'The Processor',
            'name'        => 'Test-Process #2',
            'region'      => 'Paris',
            'targets'     => ['some target', 'others'],
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
            'targets'     => ['some target', 'others'],
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
            'targets'     => ['some target', 'others'],
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
        $process->setTargets(['first target', 'second target']);
        $process->setRegion('Dresden');
        $process->setImprint('FCP Test');
        $em->persist($process);
        $em->flush();

        $iri = $this->findIriBy(Process::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'description' => 'something',
            'imprint'     => 'The Processor',
            'name'        => 'Test-Process äüöß',
            'region'      => 'Paris',
            'targets'     => ['some target', 'others'],
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
            'description' => 'something',
            'imprint'     => 'The Processor',
            'name'        => '',
            'region'      => 'Paris',
            'targets'     => ['some target', 'others'],
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

    public function testDeleteProcess(): void
    {
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
}
