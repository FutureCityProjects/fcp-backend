<?php
declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\Fund;
use App\Entity\Process;
use App\PHPUnit\AuthenticatedClientTrait;
use App\PHPUnit\RefreshDatabaseTrait;

/**
 * @group FundApi
 */
class FundApiTest extends ApiTestCase
{
    use AuthenticatedClientTrait;
    use RefreshDatabaseTrait;

    public function testGetCollection(): void
    {
        $response = static::createClient()
            ->request('GET', '/funds');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceCollectionJsonSchema(Fund::class);

        self::assertJsonContains([
            '@context'         => '/contexts/Fund',
            '@id'              => '/funds',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
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
                    'state'           => Fund::STATE_INACTIVE,
                    'budget'          => 50000,
                    'minimumGrant'    => 1000,
                    'maximumGrant'    => 5000,
                    'submissionBegin' => '2019-11-30T23:00:00+00:00',
                    'submissionEnd'   => '2019-12-30T23:00:00+00:00',
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

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context'         => '/contexts/Fund',
            '@id'              => '/funds',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
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
                    'state'           => Fund::STATE_INACTIVE,
                    'budget'          => 50000,
                    'minimumGrant'    => 1000,
                    'maximumGrant'    => 5000,
                    'submissionBegin' => '2019-11-30T23:00:00+00:00',
                    'submissionEnd'   => '2019-12-30T23:00:00+00:00',
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

        self::assertJsonContains([
            '@context'         => '/contexts/Fund',
            '@id'              => '/funds',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
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
                    'state'           => Fund::STATE_INACTIVE,
                    'budget'          => 50000,
                    'minimumGrant'    => 1000,
                    'maximumGrant'    => 5000,
                    'submissionBegin' => '2019-11-30T23:00:00+00:00',
                    'submissionEnd'   => '2019-12-30T23:00:00+00:00',
                    'ratingBegin'     => '2020-01-01T23:00:00+00:00',
                    'ratingEnd'       => '2020-01-15T23:00:00+00:00',
                    'briefingDate'    => '2020-01-16T23:00:00+00:00',
                    'finalJuryDate'   => '2020-01-31T23:00:00+00:00',
                    'process'         => [
                        'id' => 1
                    ],
                    'juryCriteria'    => [
                        0 => [
                            'name'     => 'Realistic expectations',
                            'question' => 'How realistic are the projects targets?',
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
            ],
        ]);

        $collection = $response->toArray();
        $this->assertCount(1, $collection['hydra:member']);
        $this->assertArrayNotHasKey('applications',
            $collection['hydra:member'][0]);
    }

    public function testGetFund(): void
    {
        $client = static::createClient();

        $iri = $this->findIriBy(Fund::class, ['id' => 1]);

        $response = $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(Fund::class);

        self::assertJsonContains([
            '@id'             => $iri,
            'id'              => 1,
            'name'            => 'Future City',
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
            'submissionBegin' => '2019-11-30T23:00:00+00:00',
            'submissionEnd'   => '2019-12-30T23:00:00+00:00',
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
    }

    public function testGetFundAsProcessOwner(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(Fund::class, ['id' => 1]);
        $response = $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'             => $iri,
            'id'              => 1,
            'name'            => 'Future City',
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
            'submissionBegin' => '2019-11-30T23:00:00+00:00',
            'submissionEnd'   => '2019-12-30T23:00:00+00:00',
            'ratingBegin'     => '2020-01-01T23:00:00+00:00',
            'ratingEnd'       => '2020-01-15T23:00:00+00:00',
            'briefingDate'    => '2020-01-16T23:00:00+00:00',
            'finalJuryDate'   => '2020-01-31T23:00:00+00:00',
            'process'         => [
                'id' => 1
            ],
            'juryCriteria'    => [
                0 => [
                    'name'     => 'Realistic expectations',
                    'question' => 'How realistic are the projects targets?',
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

        $iri = $this->findIriBy(Fund::class, ['id' => 1]);
        $response = $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'             => $iri,
            'id'              => 1,
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
            'state'           => Fund::STATE_INACTIVE,
            'submissionBegin' => '2019-11-30T23:00:00+00:00',
            'submissionEnd'   => '2019-12-30T23:00:00+00:00',
            'ratingBegin'     => '2020-01-01T23:00:00+00:00',
            'ratingEnd'       => '2020-01-15T23:00:00+00:00',
            'briefingDate'    => '2020-01-16T23:00:00+00:00',
            'finalJuryDate'   => '2020-01-31T23:00:00+00:00',
            'process'         => [
                'id' => 1
            ],
            'juryCriteria'    => [
                0 => [
                    'name'     => 'Realistic expectations',
                    'question' => 'How realistic are the projects targets?',
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

        $response = $client->request('POST', '/funds', ['json' => [
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
            'id'          => 2,
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

    public function testCreateWithStateFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);
        $client->request('POST', '/funds', ['json' => [
            'description' => 'just for fun',
            'name'        => 'The Future',
            'process'     => $processIri,
            'region'      => 'Berlin',
            'sponsor'     => 'sponsor',
            'state'       => Fund::STATE_ACTIVE,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Extra attributes are not allowed ("state" are unknown).',
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
            'hydra:description' => 'name: This value should not be blank.',
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
            'hydra:description' => 'process: This value should not be null.',
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
            'hydra:description' => 'description: This value should not be null.',
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
            'hydra:description' => 'region: This value should not be null.',
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
            'hydra:description' => 'sponsor: This value should not be null.',
        ]);
    }

    public function testCreateWithDuplicateNameFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);
        $client->request('POST', '/funds', ['json' => [
            'name'        => 'Future City',
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
            'hydra:description' => 'criteria[0]: This value should not be blank.',
            'violations' => [
                [
                    'propertyPath' => 'criteria[0]',
                    'message'      => 'This value should not be blank.'
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
            'hydra:description' => 'criteria[0]: This value is too short.',
            'violations' => [
                [
                    'propertyPath' => 'criteria[0]',
                    'message'      => 'This value is too short.'
                ]
            ],
        ]);
    }

    public function testUpdateFund(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $iri = $this->findIriBy(Fund::class, ['id' => 1]);

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
        $iri = $this->findIriBy(Fund::class, ['id' => 1]);

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

        $iri = $this->findIriBy(Fund::class, ['id' => 1]);
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

        // add a second fund to the db, we will try to name it like the first
        $em = static::$kernel->getContainer()->get('doctrine')->getManager();

        $process = $em->getRepository(Process::class)->find(1);
        $fund = new Fund();
        $fund->setName('New Fund');
        $fund->setRegion('Dresden');
        $fund->setDescription('new description');
        $fund->setSponsor('Test');
        $process->addFund($fund);
        $em->persist($fund);
        $em->flush();

        $iri = $this->findIriBy(Fund::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'description' => 'description with 20 characters',
            'name'        => 'Future City',
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

        $iri = $this->findIriBy(Fund::class, ['id' => 1]);
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
            'hydra:description' => 'name: This value should not be blank.',
        ]);
    }

    public function testUpdateWithProcessFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $iri = $this->findIriBy(Fund::class, ['id' => 1]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);

        $client->request('PUT', $iri, ['json' => [
            'description' => 'description with 20 characters',
            'name'        => 'New Name!',
            'process'     => $processIri,
            'region'      => 'Berlin-Hohenschönhausen',
            'sponsor'     => 'Bundesministerium',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Extra attributes are not allowed ("process" are unknown).',
        ]);
    }


    public function testUpdateWithoutDescriptionFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(Fund::class, ['id' => 1]);
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
            'hydra:description' => 'description: This value is too short.',
        ]);
    }

    public function testUpdateWithoutSponsorFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(Fund::class, ['id' => 1]);
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
            'hydra:description' => 'sponsor: This value is too short.',
        ]);
    }

    public function testUpdateWithoutRegionFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(Fund::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'description' => 'description with 20 characters',
            'name'        => 'Test',
            'region'      => '',
            'sponsor'     => 'Bundesministerium',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'region: This value is too short.',
        ]);
    }

    public function testUpdateWithEmptyCriterionFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(Fund::class, ['id' => 1]);
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
            'hydra:description' => 'criteria[0]: This value should not be blank.',
            'violations' => [
                [
                    'propertyPath' => 'criteria[0]',
                    'message'      => 'This value should not be blank.'
                ]
            ],
        ]);
    }

    public function testDeleteFund(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);
        $iri = $this->findIriBy(Fund::class, ['id' => 1]);
        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        $deleted = static::$container->get('doctrine')
            ->getRepository(Fund::class)
            ->find(1);
        $this->assertNull($deleted);
    }

    public function testDeleteFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(Fund::class, ['id' => 1]);
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
            'email' => TestFixtures::JUROR['email']
        ]);

        $iri = $this->findIriBy(Fund::class, ['id' => 1]);
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

    public function testDeleteFailsWhenFundIsActive(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        /* @var $fund Fund */
        $fund = $em->getRepository(Fund::class)->find(1);
        $fund->setState(Fund::STATE_ACTIVE);
        $em->flush();

        $iri = $this->findIriBy(Fund::class, ['id' => 1]);
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
     * @todo
     * * updating state to "active" fails when not all required fields are set
     * * updating relevant fields fails when state is active
     * * delete should be possible for PO under some conditions (active9
     * * delete should not be possible (even for admin) under some conditions
     */
}
