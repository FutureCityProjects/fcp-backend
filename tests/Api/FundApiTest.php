<?php
declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\Fund;
use App\Entity\Process;
use App\Entity\UserObjectRole;
use App\PHPUnit\AuthenticatedClientTrait;
use App\PHPUnit\RefreshDatabaseTrait;

/**
 * @group FundApi
 */
class FundApiTest extends ApiTestCase
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
            ->request('GET', '/funds');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceCollectionJsonSchema(Fund::class);

        $tz = new \DateTimeZone('UTC');
        self::assertJsonContains([
            '@context'         => '/contexts/Fund',
            '@id'              => '/funds',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
            'hydra:member'     => [
                0 => [
                    'id'              => TestFixtures::ACTIVE_FUND['id'],
                    'name'            => 'Future City',
                    'region'          => 'Dresden',
                    'description'     => 'Funding from the BMBF',
                    'criteria'        => [
                        'must be sustainable',
                    ],
                    'sponsor'         => 'Bundesministerium für Forschung und Bildung',
                    'imprint'         => 'Landeshauptstadt Dresden',
                    'state'           => Fund::STATE_ACTIVE,
                    'budget'          => 50000,
                    'minimumGrant'    => 1000,
                    'maximumGrant'    => 5000,
                    'submissionBegin' => (new \DateTimeImmutable('tomorrow +1day', $tz))
                        ->format(\DateTimeInterface::ATOM),
                    'submissionEnd'   =>  (new \DateTimeImmutable('tomorrow +2days', $tz))
                        ->format(\DateTimeInterface::ATOM),
                    'concretizations' => [
                        0 => [
                            'question'    => 'How does it help?',
                            'description' => 'What does the project do for you?',
                            'maxLength'   => 280,
                        ],
                    ],
                ],
            ],
        ]);

        $collection = $response->toArray();

        // the inactive fund is NOT returned
        $this->assertCount(1, $collection['hydra:member']);

        $this->assertArrayNotHasKey('applications',
            $collection['hydra:member'][0]);
        $this->assertArrayNotHasKey('briefingDate',
            $collection['hydra:member'][0]);
        $this->assertArrayNotHasKey('ratingBegin',
            $collection['hydra:member'][0]);
        $this->assertArrayNotHasKey('ratingEnd',
            $collection['hydra:member'][0]);
        $this->assertArrayNotHasKey('finalJuryDate',
            $collection['hydra:member'][0]);
        $this->assertArrayNotHasKey('juryCriteria',
            $collection['hydra:member'][0]);
        $this->assertArrayNotHasKey('jurorsPerApplication',
            $collection['hydra:member'][0]);
    }

    public function testGetCollectionByProcess(): void
    {
        $response = static::createClient()->request('GET', '/funds', [
            'query' => ['process' => 1]
        ]);

        $tz = new \DateTimeZone('UTC');
        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context'         => '/contexts/Fund',
            '@id'              => '/funds',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
            'hydra:member'     => [
                0 => [
                    'id'              => TestFixtures::ACTIVE_FUND['id'],
                    'name'            => 'Future City',
                    'region'          => 'Dresden',
                    'description'     => 'Funding from the BMBF',
                    'criteria'        => [
                        'must be sustainable',
                    ],
                    'sponsor'         => 'Bundesministerium für Forschung und Bildung',
                    'imprint'         => 'Landeshauptstadt Dresden',
                    'state'           => Fund::STATE_ACTIVE,
                    'budget'          => 50000,
                    'minimumGrant'    => 1000,
                    'maximumGrant'    => 5000,
                    'submissionBegin' => (new \DateTimeImmutable('tomorrow +1day', $tz))
                        ->format(\DateTimeInterface::ATOM),
                    'submissionEnd'   => (new \DateTimeImmutable('tomorrow +2days', $tz))
                        ->format(\DateTimeInterface::ATOM),
                    'process'         => [
                        'id' => 1
                    ],
                    'concretizations' => [
                        0 => [
                            'question'    => 'How does it help?',
                            'description' => 'What does the project do for you?',
                            'maxLength'   => 280,
                        ],
                    ],
                ],
            ],
        ]);

        $collection = $response->toArray();

        // the inactive fund is NOT returned
        $this->assertCount(1, $collection['hydra:member']);

        $this->assertArrayNotHasKey('applications',
            $collection['hydra:member'][0]);
    }

    public function testGetCollectionAsProcessOwner(): void
    {
        $response = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ])->request('GET', '/funds');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceCollectionJsonSchema(Fund::class);

        $tz = new \DateTimeZone('UTC');
        self::assertJsonContains([
            '@context'         => '/contexts/Fund',
            '@id'              => '/funds',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 2,
            'hydra:member'     => [
                0 => [
                    'id'              => 1,
                    'name'            => 'Future City',
                    'region'          => 'Dresden',
                    'description'     => 'Funding from the BMBF',
                    'criteria'        => [
                        'must be sustainable',
                    ],
                    'sponsor'         => 'Bundesministerium für Forschung und Bildung',
                    'imprint'         => 'Landeshauptstadt Dresden',
                    'state'           => Fund::STATE_ACTIVE,
                    'budget'          => 50000,
                    'minimumGrant'    => 1000,
                    'maximumGrant'    => 5000,
                    'submissionBegin' => (new \DateTimeImmutable('tomorrow +1day', $tz))
                        ->format(\DateTimeInterface::ATOM),
                    'submissionEnd'   => (new \DateTimeImmutable('tomorrow +2days', $tz))
                        ->format(\DateTimeInterface::ATOM),
                    'ratingBegin'     => (new \DateTimeImmutable('tomorrow +3days', $tz))
                        ->format(\DateTimeInterface::ATOM),
                    'ratingEnd'       => (new \DateTimeImmutable('tomorrow +5days', $tz))
                        ->format(\DateTimeInterface::ATOM),
                    'briefingDate'    => (new \DateTimeImmutable('tomorrow +4days', $tz))
                        ->format(\DateTimeInterface::ATOM),
                    'finalJuryDate'   => (new \DateTimeImmutable('tomorrow +6days', $tz))
                        ->format(\DateTimeInterface::ATOM),
                    'process'         => [
                        'id' => 1
                    ],
                    'juryCriteria'    => [
                        0 => [
                            'name'     => 'Realistic expectations',
                            'question' => 'How realistic are the projects goals?',
                        ],
                    ],
                    'concretizations' => [
                        0 => [
                            'question'    => 'How does it help?',
                            'description' => 'What does the project do for you?',
                            'maxLength'   => 280,
                        ],
                    ],
                    'jurorsPerApplication' => 3,
                ],
                1 => [
                    'id'              => TestFixtures::INACTIVE_FUND['id'],
                    'name'            => 'Culture City',
                    'region'          => 'Dresden',
                    'description'     => 'Funding from the BMBF',
                    'criteria'        => [
                        'must be sustainable',
                    ],
                    'sponsor'         => 'Bundesministerium für Forschung und Bildung',
                    'imprint'         => 'Landeshauptstadt Dresden',
                    'state'           => Fund::STATE_INACTIVE,
                    'budget'          => 50000,
                    'minimumGrant'    => 1000,
                    'maximumGrant'    => 5000,
                    'submissionBegin' => (new \DateTimeImmutable('tomorrow +1day', $tz))
                        ->format(\DateTimeInterface::ATOM),
                    'submissionEnd'   => (new \DateTimeImmutable('tomorrow +2days', $tz))
                        ->format(\DateTimeInterface::ATOM),
                    'concretizations' => [
                        0 => [
                            'question'    => 'How does it help?',
                            'description' => 'What does the project do for you?',
                            'maxLength'   => 280,
                        ],
                    ],
                ],
            ],
        ]);

        $collection = $response->toArray();
        $this->assertCount(2, $collection['hydra:member']);
        $this->assertArrayNotHasKey('applications',
            $collection['hydra:member'][0]);
    }

    public function testGetFund(): void
    {
        $client = static::createClient();

        $iri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::ACTIVE_FUND['id']]);

        $response = $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(Fund::class);

        $tz = new \DateTimeZone('UTC');
        self::assertJsonContains([
            '@id'             => $iri,
            'id'              => TestFixtures::ACTIVE_FUND['id'],
            'name'            => 'Future City',
            'region'          => 'Dresden',
            'description'     => 'Funding from the BMBF',
            'criteria'        => [
                'must be sustainable',
            ],
            'sponsor'         => 'Bundesministerium für Forschung und Bildung',
            'imprint'         => 'Landeshauptstadt Dresden',
            'state'           => Fund::STATE_ACTIVE,
            'budget'          => 50000,
            'minimumGrant'    => 1000,
            'maximumGrant'    => 5000,
            'submissionBegin' => (new \DateTimeImmutable('tomorrow +1day', $tz))
            ->format(\DateTimeInterface::ATOM),
            'submissionEnd'   =>  (new \DateTimeImmutable('tomorrow +2days', $tz))
                ->format(\DateTimeInterface::ATOM),
            'process'         => [
                'id' => 1
            ],
            'concretizations' => [
                0 => [
                    'question'    => 'How does it help?',
                    'description' => 'What does the project do for you?',
                    'maxLength'   => 280,
                ],
            ],
        ]);

        $fundData = $response->toArray();
        $this->assertArrayNotHasKey('applications', $fundData);
        $this->assertArrayNotHasKey('briefingDate', $fundData);
        $this->assertArrayNotHasKey('ratingBegin', $fundData);
        $this->assertArrayNotHasKey('ratingEnd', $fundData);
        $this->assertArrayNotHasKey('finalJuryDate', $fundData);
        $this->assertArrayNotHasKey('juryCriteria', $fundData);
        $this->assertArrayNotHasKey('jurorsPerApplication', $fundData);
        $this->assertArrayNotHasKey('fund', $fundData['concretizations']);
    }

    public function testGetFundAsProcessOwner(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::ACTIVE_FUND['id']]);
        $response = $client->request('GET', $iri);

        $tz = new \DateTimeZone('UTC');
        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'             => $iri,
            'id'              => TestFixtures::ACTIVE_FUND['id'],
            'name'            => 'Future City',
            'region'          => 'Dresden',
            'description'     => 'Funding from the BMBF',
            'criteria'        => [
                'must be sustainable',
            ],
            'sponsor'         => 'Bundesministerium für Forschung und Bildung',
            'imprint'         => 'Landeshauptstadt Dresden',
            'state'           => Fund::STATE_ACTIVE,
            'budget'          => 50000,
            'minimumGrant'    => 1000,
            'maximumGrant'    => 5000,
            'submissionBegin' => (new \DateTimeImmutable('tomorrow +1day', $tz))
                ->format(\DateTimeInterface::ATOM),
            'submissionEnd'   => (new \DateTimeImmutable('tomorrow +2days', $tz))
                ->format(\DateTimeInterface::ATOM),
            'ratingBegin'     => (new \DateTimeImmutable('tomorrow +3days', $tz))
                ->format(\DateTimeInterface::ATOM),
            'ratingEnd'       => (new \DateTimeImmutable('tomorrow +5days', $tz))
                ->format(\DateTimeInterface::ATOM),
            'briefingDate'    => (new \DateTimeImmutable('tomorrow +4days', $tz))
                ->format(\DateTimeInterface::ATOM),
            'finalJuryDate'   => (new \DateTimeImmutable('tomorrow +6days', $tz))
                ->format(\DateTimeInterface::ATOM),
            'process'         => [
                'id' => 1
            ],
            'juryCriteria'    => [
                0 => [
                    'name'     => 'Realistic expectations',
                    'question' => 'How realistic are the projects goals?',
                ],
            ],
            'concretizations' => [
                0 => [
                    'question'    => 'How does it help?',
                    'description' => 'What does the project do for you?',
                    'maxLength'   => 280,
                ],
            ],
            'jurorsPerApplication' => 3,
        ]);

        $data = $response->toArray();

        // the applications should not be fetched, there could be many,
        // it's better to fetch the application collection and filter by fund
        $this->assertArrayNotHasKey('applications', $data);
    }

    public function testGetFundAsJuror(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::JUROR['email']
        ]);

        $iri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::ACTIVE_FUND['id']]);
        $response = $client->request('GET', $iri);

        $tz = new \DateTimeZone('UTC');
        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'             => $iri,
            'id'              => TestFixtures::ACTIVE_FUND['id'],
            'name'            => 'Future City',
            'region'          => 'Dresden',
            'description'     => 'Funding from the BMBF',
            'criteria'        => [
                'must be sustainable',
            ],
            'sponsor'         => 'Bundesministerium für Forschung und Bildung',
            'imprint'         => 'Landeshauptstadt Dresden',
            'budget'          => 50000,
            'minimumGrant'    => 1000,
            'maximumGrant'    => 5000,
            'state'           => Fund::STATE_ACTIVE,
            'submissionBegin' => (new \DateTimeImmutable('tomorrow +1day', $tz))
                ->format(\DateTimeInterface::ATOM),
            'submissionEnd'   => (new \DateTimeImmutable('tomorrow +2days', $tz))
                ->format(\DateTimeInterface::ATOM),
            'ratingBegin'     => (new \DateTimeImmutable('tomorrow +3days', $tz))
                ->format(\DateTimeInterface::ATOM),
            'ratingEnd'       => (new \DateTimeImmutable('tomorrow +5days', $tz))
                ->format(\DateTimeInterface::ATOM),
            'briefingDate'    => (new \DateTimeImmutable('tomorrow +4days', $tz))
                ->format(\DateTimeInterface::ATOM),
            'finalJuryDate'   => (new \DateTimeImmutable('tomorrow +6days', $tz))
                ->format(\DateTimeInterface::ATOM),
            'process'         => [
                'id' => 1
            ],
            'juryCriteria'    => [
                0 => [
                    'name'     => 'Realistic expectations',
                    'question' => 'How realistic are the projects goals?',
                ],
            ],
            'concretizations' => [
                0 => [
                    'question'    => 'How does it help?',
                    'description' => 'What does the project do for you?',
                    'maxLength'   => 280,
                ],
            ],
            'jurorsPerApplication' => 3,
        ]);

        $data = $response->toArray();
        // the juror should not see all applications, only those he has access to
        // -> via his juryRatings
        $this->assertArrayNotHasKey('applications', $data);
    }

    public function testCreateFund(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);

        $client->request('POST', '/funds', ['json' => [
            'description' => 'description with 20 characters',
            'name'        => 'Masterfund',
            'process'     => $processIri,
            'region'      => 'Berlin',
            'sponsor'     => 'Bundesministerium',
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(Fund::class);

        self::assertJsonContains([
            '@context'    => '/contexts/Fund',
            '@type'       => 'Fund',
            'id'          => 3, // 1-2 created by fixtures
            'description' => 'description with 20 characters',
            'name'        => 'Masterfund',
            'slug'        => 'masterfund',
            'process'     => [
                'id' => 1
            ],
            'region'      => 'Berlin',
            'sponsor'     => 'Bundesministerium',

            // defaults
            'state'                => Fund::STATE_INACTIVE,
            'budget'               => null,
            'minimumGrant'         => null,
            'maximumGrant'         => null,
            'imprint'              => null,
            'submissionBegin'      => null,
            'submissionEnd'        => null,
            'concretizations'      => [],
            'criteria'             => null,
            'ratingBegin'          => null,
            'ratingEnd'            => null,
            'briefingDate'         => null,
            'finalJuryDate'        => null,
            'juryCriteria'         => [],
            'jurorsPerApplication' => 2,
        ]);
    }

    public function testCreateFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);
        $client->request('POST', '/funds', ['json' => [
            'description' => 'just for fun',
            'name'        => 'Masterfund',
            'process'     => $processIri,
            'region'      => 'Berlin',
            'sponsor'     => 'sponsor',
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
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $processIri = $this->findIriBy(Process::class, ['id' => 1]);
        $client->request('POST', '/funds', ['json' => [
            'description' => 'just for fun',
            'name'        => 'Masterfund',
            'process'     => $processIri,
            'region'      => 'Berlin',
            'sponsor'     => 'sponsor',
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

    public function testStateIsIgnoredOnCreation(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);
        $client->request('POST', '/funds', ['json' => [
            'description' => 'description with 20 characters',
            'name'        => 'The Future',
            'process'     => $processIri,
            'region'      => 'Berlin',
            'sponsor'     => 'sponsor name',
            'state'       => Fund::STATE_ACTIVE,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'description' => 'description with 20 characters',
            'name'        => 'The Future',
            'state'       => Fund::STATE_INACTIVE,
        ]);
    }

    public function testCreateWithoutNameFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);
        $client->request('POST', '/funds', ['json' => [
            'description' => 'description with 20 characters',
            'process'     => $processIri,
            'region'      => 'Berlin',
            'sponsor'     => 'Bundesministerium',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'name: validate.general.notBlank',
        ]);
    }

    public function testCreateWithoutProcessFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $client->request('POST', '/funds', ['json' => [
            'description' => 'description with 20 characters',
            'name'        => 'Will fail',
            'region'      => 'Berlin',
            'sponsor'     => 'Bundesministerium',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'process: validate.general.notBlank',
        ]);
    }

    public function testCreateWithoutDescriptionFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);
        $client->request('POST', '/funds', ['json' => [
            'name'    => 'Test',
            'process' => $processIri,
            'region'  => 'Berlin',
            'sponsor' => 'Bundesministerium',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'description: validate.general.notBlank',
        ]);
    }

    public function testCreateWithTooShortDescriptionFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);
        $client->request('POST', '/funds', ['json' => [
            'name'    => 'Test',
            'process' => $processIri,
            'region'  => 'Berlin',
            'sponsor' => 'Bundesministerium',

            // only 19 chars w/o tags and w/ trim
            'description' => ' <p>0123465789012345678</p> '
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'description: validate.general.tooShort',
        ]);
    }

    public function testCreateWithoutRegionFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);
        $client->request('POST', '/funds', ['json' => [
            'description' => 'description with 20 characters',
            'process'     => $processIri,
            'name'        => 'Berlin',
            'sponsor'     => 'Bundesministerium für Forschung',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'region: validate.general.notBlank',
        ]);
    }

    public function testCreateWithTooShortRegionFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);
        $client->request('POST', '/funds', ['json' => [
            'description' => 'description with 20 characters',
            'process'     => $processIri,
            'name'        => 'Berlin',
            'region'      => 'DD',
            'sponsor'     => 'Bundesministerium für Forschung',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'region: validate.general.tooShort',
        ]);
    }

    public function testCreateWithTooLongRegionFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);

        $hash = hash('sha512', '1', false);
        $client->request('POST', '/funds', ['json' => [
            'description' => 'description with 20 characters',
            'process'     => $processIri,
            'name'        => 'Berlin',
            'region'      => $hash.$hash, // 256 vs 255 allowed
            'sponsor'     => 'Bundesministerium für Forschung',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'region: validate.general.tooLong',
        ]);
    }

    public function testCreateWithoutSponsorFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);
        $client->request('POST', '/funds', ['json' => [
            'description' => 'description with 20 characters',
            'name'        => 'Test',
            'process'     => $processIri,
            'region'      => 'Berlin',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'sponsor: validate.general.notBlank',
        ]);
    }

    public function testCreateWithDuplicateNameFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);
        $client->request('POST', '/funds', ['json' => [
            'name'        => TestFixtures::ACTIVE_FUND['name'],
            'description' => 'description with 20 characters',
            'process'     => $processIri,
            'region'      => 'Berlin',
            'sponsor'     => 'Bundesministerium',
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

    public function testCreateWithInvalidCriteriaFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);
        $client->request('POST', '/funds', ['json' => [
            'name'        => 'The Future',
            'description' => 'just for fun',
            'process'     => $processIri,
            'region'      => 'Berlin',
            'sponsor'     => 'sponsor',
            'criteria'    => 'none', // should be an array to work
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'The type of the "criteria" attribute must be "array", "string" given.',
        ]);
    }

    public function testCreateWithEmptyCriterionFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);
        $client->request('POST', '/funds', ['json' => [
            'name'        => 'The Future',
            'description' => 'description with 20 characters',
            'process'     => $processIri,
            'region'      => 'Berlin',
            'sponsor'     => 'Bundesministerium',
            'criteria'    => [null] // minlength = 5
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'criteria[0]: validate.general.notBlank',
            'violations' => [
                [
                    'propertyPath' => 'criteria[0]',
                    'message'      => 'validate.general.notBlank'
                ]
            ],
        ]);
    }

    public function testCreateWithShortCriterionFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);
        $client->request('POST', '/funds', ['json' => [
            'name'        => 'The Future',
            'description' => 'description with 20 characters',
            'process'     => $processIri,
            'region'      => 'Berlin',
            'sponsor'     => 'Bundesministerium für Forschung',
            'criteria'    => ['123'] // minlength = 5
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'criteria[0]: validate.general.tooShort',
            'violations' => [
                [
                    'propertyPath' => 'criteria[0]',
                    'message'      => 'validate.general.tooShort'
                ]
            ],
        ]);
    }

    public function testUpdateFund(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $iri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::ACTIVE_FUND['id']]);

        $client->request('PUT', $iri, ['json' => [
            'description' => 'description with 20 characters',
            'name'        => 'New Name!',
            'region'      => 'Berlin-Hohenschönhausen',
            'sponsor'     => 'Bundesministerium',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'         => $iri,
            'description' => 'description with 20 characters',
            'name'        => 'New Name!',
            'slug'        => 'new-name',
            'region'      => 'Berlin-Hohenschönhausen',
            'sponsor'     => 'Bundesministerium',
            'imprint'     => 'Landeshauptstadt Dresden',
        ]);
    }

    public function testUpdateFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::ACTIVE_FUND['id']]);

        $client->request('PUT', $iri, ['json' => [
            'description' => 'new Description',
            'name'        => 'New Name!',
            'region'      => 'Berlin-Hohenschönhausen',
            'sponsor'     => 'BMBF',
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

        $iri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::ACTIVE_FUND['id']]);
        $client->request('PUT', $iri, ['json' => [
            'description' => 'new Description',
            'name'        => 'New Name!',
            'region'      => 'Berlin-Hohenschönhausen',
            'sponsor'     => 'BMBF',
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

        $iri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::ACTIVE_FUND['id']]);
        $client->request('PUT', $iri, ['json' => [
            'description' => 'description with 20 characters',
            'name'        => TestFixtures::INACTIVE_FUND['name'],
            'region'      => 'Paris',
            'sponsor'     => 'Bundesministerium',
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

    public function testUpdateWithoutNameFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::ACTIVE_FUND['id']]);
        $client->request('PUT', $iri, ['json' => [
            'description' => 'description with 20 characters',
            'name'        => '',
            'region'      => 'Berlin-Hohenschönhausen',
            'sponsor'     => 'Bundesministerium',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'name: validate.general.notBlank',
        ]);
    }

    public function testUpdateOfProcessIsIgnored(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $iri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::ACTIVE_FUND['id']]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);

        $client->request('PUT', $iri, ['json' => [
            'name'        => 'New Name!',
            'process'     => str_replace("1", "2", $processIri),
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'         => $iri,
            'name'        => 'New Name!',
            'process'     => [
                '@id' => $processIri,
                'id'  => 1,
            ],
        ]);
    }

    public function testUpdateWithoutDescriptionFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::ACTIVE_FUND['id']]);
        $client->request('PUT', $iri, ['json' => [
            'description' => '',
            'name'        => 'Test',
            'region'      => 'Berlin-Hohenschönhausen',
            'sponsor'     => 'Bundesministerium',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'description: validate.general.notBlank',
        ]);
    }

    public function testUpdateWithEmptySponsorFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::ACTIVE_FUND['id']]);
        $client->request('PUT', $iri, ['json' => [
            'description' => 'description with 20 characters',
            'name'        => 'Test',
            'region'      => 'Berlin-Hohenschönhausen',
            'sponsor'     => '',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'sponsor: validate.general.notBlank',
        ]);
    }

    public function testUpdateWithEmptyRegionFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::ACTIVE_FUND['id']]);
        $client->request('PUT', $iri, ['json' => [
            'description' => 'description with 20 characters',
            'name'        => 'Test',
            'region'      => ' ',
            'sponsor'     => 'Bundesministerium',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'region: validate.general.notBlank',
        ]);
    }

    public function testUpdateWithEmptyCriterionFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::ACTIVE_FUND['id']]);
        $client->request('PUT', $iri, ['json' => [
            'description' => 'description with 20 characters',
            'name'        => 'Test',
            'region'      => 'Berlin-Hohenschönhausen',
            'sponsor'     => 'Bundesministerium',
            'criteria'    => [null] // minlength = 5
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'criteria[0]: validate.general.notBlank',
            'violations' => [
                [
                    'propertyPath' => 'criteria[0]',
                    'message'      => 'validate.general.notBlank'
                ]
            ],
        ]);
    }

    public function testUpdateWithUnknownStateFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::ACTIVE_FUND['id']]);
        $client->request('PUT', $iri, ['json' => [
            'description' => 'description with 20 characters',
            'name'        => 'Test',
            'region'      => 'Berlin-Hohenschönhausen',
            'sponsor'     => 'Bundesministerium',
            'state'       => 'unknown',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'state: The value you selected is not a valid choice.',
        ]);
    }

    public function testDelete(): void
    {
        $before = $this->entityManager->getRepository(UserObjectRole::class)
            ->findBy(['objectId' => TestFixtures::INACTIVE_FUND['id'], 'objectType' => Fund::class]);
        $this->assertCount(1, $before);

        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);
        $iri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::INACTIVE_FUND['id']]);
        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        $deleted = static::$container->get('doctrine')
            ->getRepository(Fund::class)
            ->find(TestFixtures::INACTIVE_FUND['id']);
        $this->assertNull($deleted);

        $after = $this->entityManager->getRepository(UserObjectRole::class)
            ->findBy(['objectId' => TestFixtures::INACTIVE_FUND['id'], 'objectType' => Fund::class]);
        $this->assertCount(0, $after);
    }

    public function testDeleteFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::INACTIVE_FUND['id']]);
        $client->request('DELETE', $iri);

        // 404 because unauthenticated users can't see inactive funds
        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'         => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Not Found',
        ]);
    }

    public function testDeleteFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::JUROR['email']
        ]);

        $iri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::INACTIVE_FUND['id']]);
        $client->request('DELETE', $iri);

        // 404 because unprivileged users can't see inactive funds
        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'         => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Not Found',
        ]);
    }

    public function testDeleteFailsWhenFundIsActive(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::ACTIVE_FUND['id']]);
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
        ])->request('DELETE', '/funds');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "DELETE /funds": Method Not Allowed (Allow: GET, POST)',
        ]);
    }

    public function testActivateFund(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $iri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::INACTIVE_FUND['id']]).'/activate';

        $client->request('POST', $iri, ['json' => []]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context' => '/contexts/Fund',
            '@type'    => 'Fund',
            'id'       => TestFixtures::INACTIVE_FUND['id'],
            'state'    => Fund::STATE_ACTIVE,
        ]);
    }

    public function testActivateFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(Fund::class,
                ['id' => TestFixtures::INACTIVE_FUND['id']]).'/activate';
        $client->request('POST', $iri, ['json' => []]);

        // 404 because unauthenticated users can't see inactive funds
        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'         => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Not Found',
        ]);
    }

    public function testActivateFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::JUROR['email']
        ]);
        $iri = $this->findIriBy(Fund::class,
                ['id' => TestFixtures::INACTIVE_FUND['id']]).'/activate';

        $client->request('POST', $iri, ['json' => []]);

        // 404 because unprivileged users can't see inactive funds
        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'         => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Not Found',
        ]);
    }

    public function testActivateFailsWithoutSubmissionPeriod(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $iri = $this->findIriBy(Fund::class,
                ['id' => TestFixtures::INACTIVE_FUND['id']]).'/activate';

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();

        $fund = $em->getRepository(Fund::class)
            ->find(TestFixtures::INACTIVE_FUND['id']);
        $fund->setSubmissionEnd(null);
        $em->flush();
        $em->clear();

        $client->request('POST', $iri, ['json' => []]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validate.fund.activationNotPossible',
        ]);
    }

    public function testActivateFailsWithInvalidSubmissionPeriod(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $iri = $this->findIriBy(Fund::class,
                ['id' => TestFixtures::INACTIVE_FUND['id']]).'/activate';

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();

        $fund = $em->getRepository(Fund::class)
            ->find(TestFixtures::INACTIVE_FUND['id']);
        $fund->setSubmissionEnd(new \DateTimeImmutable('2000-12-12'));
        $em->flush();
        $em->clear();

        $client->request('POST', $iri, ['json' => []]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validate.fund.activationNotPossible',
        ]);
    }

    public function testActivateFailsWithoutConcretization(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $iri = $this->findIriBy(Fund::class,
                ['id' => TestFixtures::INACTIVE_FUND['id']]).'/activate';

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();

        $fund = $em->getRepository(Fund::class)
            ->find(TestFixtures::INACTIVE_FUND['id']);
        foreach($fund->getConcretizations() as $concretization) {
            $em->remove($concretization);
        }
        $em->flush();
        $em->clear();

        $client->request('POST', $iri, ['json' => []]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validate.fund.activationNotPossible',
        ]);
    }

    public function testActivateFailsWIthActiveFund(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $iri = $this->findIriBy(Fund::class,
                ['id' => TestFixtures::ACTIVE_FUND['id']]).'/activate';

        $client->request('POST', $iri, ['json' => []]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validate.fund.activationNotPossible',
        ]);
    }

    // @todo
    // * only active funds are public item|coll
    // * updating state to "active" fails when not all required fields are set
    // * updating relevant fields fails when state is active
    // * delete should be possible for PO under some conditions
    // * delete should not be possible (even for admin) under some conditions
}
