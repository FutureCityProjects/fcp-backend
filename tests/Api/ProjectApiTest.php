<?php
declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Dto\ResourceInput;
use App\Entity\Process;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\PHPUnit\AuthenticatedClientTrait;
use App\PHPUnit\RefreshDatabaseTrait;
use DateTimeImmutable;

/**
 * @group ProjectApi
 */
class ProjectApiTest extends ApiTestCase
{
    use AuthenticatedClientTrait;
    use RefreshDatabaseTrait;

    /**
     * Test what anonymous users see
     */
    public function testGetCollection(): void
    {
        $response = static::createClient()
            ->request('GET', '/projects');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceCollectionJsonSchema(Project::class);

        self::assertJsonContains([
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 2,
            'hydra:member'     => [
                0 => [
                    'id'                => TestFixtures::IDEA['id'],
                    'createdBy'             => [
                        'id' => TestFixtures::ADMIN['id']
                    ],
                    'resultingProjects' => [
                        [
                            'id' => TestFixtures::PROJECT['id'],
                        ]
                    ]
                ],
                1 => [
                    'id' => TestFixtures::PROJECT['id'],
                    'createdBy'             => [
                        'id' => TestFixtures::PROJECT_OWNER['id']
                    ],
                ],
            ],
        ]);

        $collection = $response->toArray();

        // the locked and the deleted project are NOT returned
        $this->assertCount(2, $collection['hydra:member']);

        // those properties should not be visible to anonymous
        $this->assertArrayNotHasKey('applications', $collection['hydra:member'][1]);
        $this->assertArrayNotHasKey('isLocked', $collection['hydra:member'][1]);
        $this->assertArrayNotHasKey('memberships', $collection['hydra:member'][1]);
        $this->assertArrayNotHasKey('firstName', $collection['hydra:member'][1]['createdBy']);
        $this->assertArrayNotHasKey('lastName', $collection['hydra:member'][1]['createdBy']);

    }

    /**
     * Test what process owners see (additional properties)
     */
    public function testGetCollectionAsProcessOwner(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $response = $client->request('GET', '/projects');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceCollectionJsonSchema(Project::class);

        self::assertJsonContains([
            '@context' => '/contexts/Project',
            '@id' => '/projects',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 2,
        ]);

        $collection = $response->toArray();
        // the locked and the deleted project are NOT returned
        $this->assertCount(2, $collection['hydra:member']);

        $this->assertSame(TestFixtures::IDEA['id'], $collection['hydra:member'][0]['id']);
        $this->assertSame(TestFixtures::PROJECT['id'], $collection['hydra:member'][1]['id']);

        // properties visible to PO
        $this->assertArrayHasKey('applications', $collection['hydra:member'][1]);
        $this->assertArrayHasKey('createdBy', $collection['hydra:member'][1]);
        $this->assertArrayHasKey('isLocked', $collection['hydra:member'][1]);
        $this->assertArrayHasKey('memberships', $collection['hydra:member'][1]);
    }

    /**
     * Filter the collection for undeleted projects only, same as default.
     */
    public function testGetUndeletedProjects(): void
    {
        $response = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ])->request('GET', '/projects', ['query' => [
            'exists[deletedAt]' => 0
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context' => '/contexts/Project',
            '@id' => '/projects',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 2,
        ]);

        // the deleted and the locked project are NOT returned
        $collection = $response->toArray();
        $this->assertCount(2, $collection['hydra:member']);

        $this->assertSame(TestFixtures::IDEA['id'], $collection['hydra:member'][0]['id']);
        $this->assertSame(TestFixtures::PROJECT['id'], $collection['hydra:member'][1]['id']);
    }

    /**
     * Admins can explicitly request deleted projects via filter.
     */
    public function testGetDeletedProjectsAsAdmin(): void
    {
        $response = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ])->request('GET', '/projects', ['query' => [
            'exists[deletedAt]' => 1
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context' => '/contexts/Project',
            '@id' => '/projects',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 1,
        ]);

        $collection = $response->toArray();

        $this->assertCount(1, $collection['hydra:member']);
        $this->assertSame(TestFixtures::DELETED_PROJECT['id'],
            $collection['hydra:member'][0]['id']);
    }

    public function testGetLockedProjectsAsProcessOwner(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $response = $client->request('GET', '/projects', [
            'query' => ['isLocked' => true]
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context' => '/contexts/Project',
            '@id' => '/projects',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 1,
        ]);

        $collection = $response->toArray();

        $this->assertCount(1, $collection['hydra:member']);
        $this->assertSame(TestFixtures::LOCKED_PROJECT['id'],
            $collection['hydra:member'][0]['id']);
    }

    /**
     * @todo replace by custom filter "mine"
     */
    public function testGetProjectsByIdAsProjectMember(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $client->request('GET', '/projects', [
            'query' => ['id' => [
                TestFixtures::PROJECT['id'],
                TestFixtures::LOCKED_PROJECT['id'],
                TestFixtures::DELETED_PROJECT['id'],

                // by IRI works too
                //$this->findIriBy(Project::class, ['id' => TestFixtures::PROJECT['id']]),
                //$this->findIriBy(Project::class, ['id' => TestFixtures::LOCKED_PROJECT['id']]),
                //$this->findIriBy(Project::class, ['id' => TestFixtures::DELETED_PROJECT['id']]),
            ]]
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context'          => '/contexts/Project',
            '@id'               => '/projects',
            '@type'             => 'hydra:Collection',

            // the deleted project is NOT returned
            'hydra:totalItems'  => 2,
            'hydra:member'      => [
                0 => ['id' => TestFixtures::PROJECT['id']],
                1 => ['id' => TestFixtures::LOCKED_PROJECT['id']]
            ],
        ]);
    }

    public function testGetProjectsByProgress(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $client->request('GET', '/projects', [
            'query' => ['progress' => [
                Project::PROGRESS_CREATING_PROFILE, // only one matching PRJ
                Project::PROGRESS_CREATING_PLAN, // only LOCKED_PRJ matching
            ]]
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context'          => '/contexts/Project',
            '@id'               => '/projects',
            '@type'             => 'hydra:Collection',

            // the deleted project is NOT returned for "normal" users
            // the locked project is only returned for "normal" users when
            // they request it via ID
            'hydra:totalItems'  => 1,
            'hydra:member'      => [
                0 => ['id' => TestFixtures::PROJECT['id']],
            ],
        ]);
    }

    public function testGetProjectIdea(): void
    {
        $client = static::createClient();

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::IDEA['id']]);

        $response = $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(Project::class);

        self::assertJsonContains([
            '@id'                   => $iri,
            'challenges'            => null,
            'createdBy'             => [
                'id'       => TestFixtures::ADMIN['id'],
                'username' => TestFixtures::ADMIN['username'],
            ],
            'delimitation'      => null,
            'description'       => null,
            'id'                => TestFixtures::IDEA['id'],
            'inspiration'       => null,
            'name'              => null,
            'progress'          => Project::PROGRESS_IDEA,
            'resultingProjects' => [
                [
                    'id' => TestFixtures::PROJECT['id'],
                ]
            ],
            'shortDescription'  => 'Car-free city center around the year',
            'slug'              => null,
            'state'             => Project::STATE_ACTIVE,
            'goal'              => null,
            'vision'            => null,
        ]);

        $projectData = $response->toArray();

        // these properties are not public
        $this->assertArrayNotHasKey('isLocked', $projectData);
        $this->assertArrayNotHasKey('memberships', $projectData);
        $this->assertArrayNotHasKey('applications', $projectData);
        $this->assertArrayNotHasKey('firstName', $projectData['createdBy']);
        $this->assertArrayNotHasKey('lastName', $projectData['createdBy']);
    }

    public function testGetProject(): void
    {
        $client = static::createClient();

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $response = $client->request('GET', $iri);

        self::assertMatchesResourceItemJsonSchema(Project::class);

        self::assertJsonContains([
            '@id'              => $iri,
            'challenges'       => 'challenges',
            'createdBy'        => [
                'id' => TestFixtures::PROJECT_OWNER['id']
            ],
            'delimitation'     => TestFixtures::PROJECT['delimitation'],
            'description'      => TestFixtures::PROJECT['description'],
            'id'               => TestFixtures::PROJECT['id'],
            'name'             => TestFixtures::PROJECT['name'],
            'progress'         => Project::PROGRESS_CREATING_PROFILE,
            'shortDescription' => TestFixtures::PROJECT['shortDescription'],
            'slug'             => 'car-free-dresden',
            'state'            => Project::STATE_ACTIVE,
            'goal'             => TestFixtures::PROJECT['goal'],
            'vision'           => TestFixtures::PROJECT['vision'],
        ]);

        $projectData = $response->toArray();
        $this->assertSame(TestFixtures::IDEA['id'], $projectData['inspiration']['id']);

        $this->assertArrayNotHasKey('applications', $projectData);
        $this->assertArrayNotHasKey('isLocked', $projectData);
        $this->assertArrayNotHasKey('memberships', $projectData);
        $this->assertArrayNotHasKey('profileSelfAssessment', $projectData);
        $this->assertArrayNotHasKey('planSelfAssessment', $projectData);
        $this->assertArrayNotHasKey('impact', $projectData);
        $this->assertArrayNotHasKey('implementationTime', $projectData);
        $this->assertArrayNotHasKey('outcome', $projectData);
        $this->assertArrayNotHasKey('results', $projectData);
        $this->assertArrayNotHasKey('targetGroups', $projectData);
        $this->assertArrayNotHasKey('tasks', $projectData);
        $this->assertArrayNotHasKey('utilization', $projectData);
        $this->assertArrayNotHasKey('workPackages', $projectData);

        $this->assertArrayNotHasKey('firstName', $projectData['createdBy']);
        $this->assertArrayNotHasKey('lastName', $projectData['createdBy']);
    }

    public function testGetProjectAsMember(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $response = $client->request('GET', $iri);
        $projectData = $response->toArray();

        self::assertMatchesResourceItemJsonSchema(Project::class);
        self::assertJsonContains([
            '@id' => $iri,
            'id' => TestFixtures::PROJECT['id'],
            'createdBy'             => [
                'id' => TestFixtures::PROJECT_OWNER['id']
            ],
            'name' => TestFixtures::PROJECT['name'],
            'applications' => [
                [
                    '@type' => 'FundApplication',
                    'id'    => 1,
                    'fund'  => [
                        '@type' => 'Fund',
                        'id'    => TestFixtures::ACTIVE_FUND['id']
                    ],
                    'concretizations' => null,
                ],
            ],
        ]);

        $this->assertSame(TestFixtures::IDEA['id'], $projectData['inspiration']['id']);

        $this->assertCount(1, $projectData['applications']);
        $this->assertArrayNotHasKey('ratings', $projectData['applications'][0]);
        $this->assertArrayNotHasKey('applications',
            $projectData['applications'][0]['fund']);

        $this->assertCount(2, $projectData['memberships']);

        // those properties are only visible to the PO/Admin
        $this->assertArrayNotHasKey('isLocked', $projectData);
    }

    public function testGetProjectAsProcessOwner(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $response = $client->request('GET', $iri);

        self::assertMatchesResourceItemJsonSchema(Project::class);
        self::assertJsonContains([
            '@id'  => $iri,
            'id'   => TestFixtures::PROJECT['id'],
            'name' => 'Car-free Dresden',
        ]);

        $projectData = $response->toArray();
        $this->assertSame(TestFixtures::IDEA['id'], $projectData['inspiration']['id']);
        $this->assertCount(1, $projectData['applications']);
        $this->assertCount(2, $projectData['memberships']);

        // those properties are only visible to the PO/Admin
        $this->assertArrayHasKey('createdBy', $projectData);
        $this->assertArrayHasKey('isLocked', $projectData);
    }

    /**
     * Anonymous users cannot get a locked project, returns 404.
     */
    public function testGetLockedProjectFailsUnauthenticated(): void
    {
        $client = static::createClient();

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::LOCKED_PROJECT['id']]);
        $client->request('GET', $iri);

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Not Found',
        ]);
    }

    /**
     * Normal users cannot get a locked project, returns 404.
     */
    public function testGetLockedProjectFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::JUROR['email']
        ]);

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::LOCKED_PROJECT['id']]);
        $client->request('GET', $iri);

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Not Found',
        ]);
    }

    public function testProcessOwnerCanGetLockedProject(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::LOCKED_PROJECT['id']]);
        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id' => $iri,
            'id' => TestFixtures::LOCKED_PROJECT['id'],
            'name' => TestFixtures::LOCKED_PROJECT['name'],
        ]);
    }

    public function testMemberCanGetLockedProject(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::LOCKED_PROJECT['id']]);
        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id' => $iri,
            'id' => TestFixtures::LOCKED_PROJECT['id'],
            'name' => TestFixtures::LOCKED_PROJECT['name'],
        ]);
    }

    public function testCreateIdea(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);

        $response = $client->request('POST', '/projects', ['json' => [
            'process'          => $processIri,
            'shortDescription' => 'just for fun',
            'progress'         => Project::PROGRESS_IDEA,
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(Project::class);

        self::assertJsonContains([
            'challenges'        => null,
            'delimitation'      => null,
            'description'       => null,
            'id'                => 5, // ID 1-4 created by fixtures
            'inspiration'       => null,
            'name'              => null,
            'progress'          => Project::PROGRESS_IDEA,
            'shortDescription'  => 'just for fun',
            'slug'              => null,
            'state'             => Project::STATE_ACTIVE,
            'goal'              => null,
            'vision'            => null,
        ]);

        $projectData = $response->toArray();
        $this->assertRegExp('~^/projects/\d+$~', $projectData['@id']);
        $this->assertArrayHasKey('id', $projectData);
        $this->assertIsInt($projectData['id']);
        $this->assertSame(TestFixtures::ADMIN['id'], $projectData['createdBy']['id']);
    }

    public function testCreateProject(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $ideaIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::IDEA['id']]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);

        $response = $client->request('POST', '/projects', ['json' => [
            'inspiration' => $ideaIRI,
            'process'     => $processIri,
            'progress'    => Project::PROGRESS_CREATING_PROFILE,
            'motivation'  => 'my motivation',
            'skills'      => 'my project skills',
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(Project::class);

        self::assertJsonContains([
            'challenges'   => null,
            'delimitation' => null,
            'description'  => null,
            'id'           => 5, // ID 1-4 created by fixtures
            'inspiration'  => [
                'id' => TestFixtures::IDEA['id'],
            ],
            'name'                  => null,
            'profileSelfAssessment' => Project::SELF_ASSESSMENT_0_PERCENT,
            'progress'              => Project::PROGRESS_CREATING_PROFILE,
            'memberships'           => [
                [
                    '@type'      => 'ProjectMembership',
                    'role'       => ProjectMembership::ROLE_OWNER,
                    'motivation' => 'my motivation',
                    'skills'     => 'my project skills',
                    'user'       => [
                        'id' => TestFixtures::ADMIN['id'],
                    ],
                ],
            ],
            'shortDescription'      => TestFixtures::IDEA['shortDescription'],
            'slug'                  => null,
            'state'                 => Project::STATE_ACTIVE,
            'goal'                  => null,
            'vision'                => null,
        ]);

        $projectData = $response->toArray();
        $this->assertSame(TestFixtures::ADMIN['id'], $projectData['createdBy']['id']);
    }

    public function testCreateFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);

        $client->request('POST', '/projects', ['json' => [
            'shortDescription' => 'just for fun',
            'progress'         => Project::PROGRESS_IDEA,
            'process'          => $processIri,
            'motivation'  => 'my motivation',
            'skills'      => 'my skills',
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testCreateIdeaWithoutProgressFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $processIri = $this->findIriBy(Process::class, ['id' => 1]);

        $client->request('POST', '/projects', ['json' => [
            'process'          => $processIri,
            'shortDescription' => 'no description',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'progress: validate.general.notBlank',
        ]);
    }

    public function testCreateWithoutProcessFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $ideaIRI = $this->findIriBy(Project::class, ['id' => 1]);

        $client->request('POST', '/projects', ['json' => [
            'inspiration' => $ideaIRI,
            'progress'    => Project::PROGRESS_CREATING_PROFILE,
            'motivation'  => 'my motivation',
            'skills'      => 'my project skills',
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

    public function testCreateIdeaWithoutShortDescriptionFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $processIri = $this->findIriBy(Process::class, ['id' => 1]);

        $client->request('POST', '/projects', ['json' => [
            'process'     => $processIri,
            'progress'    => Project::PROGRESS_IDEA,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'shortDescription: validate.general.notBlank',
        ]);
    }

    public function testCreateProjectWithoutInspirationFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $processIri = $this->findIriBy(Process::class, ['id' => 1]);

        $client->request('POST', '/projects', ['json' => [
            'name'        => 'just for fun',
            'process'     => $processIri,
            'progress'    => Project::PROGRESS_CREATING_PROFILE,
            'motivation'  => 'my motivation',
            'skills'      => 'my project skills',
            'shortDescription' => 'not required, only for this test',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'inspiration: validate.project.inspiration.notBlank',
        ]);
    }

    public function testCreateProjectWithoutMotivationFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $ideaIRI = $this->findIriBy(Project::class, ['id' => 1]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);

        $client->request('POST', '/projects', ['json' => [
            'inspiration' => $ideaIRI,
            'process'     => $processIri,
            'progress'    => Project::PROGRESS_CREATING_PROFILE,
            'skills'      => 'my project skills',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'motivation: validate.general.notBlank',
        ]);
    }

    public function testCreateProjectWithShortMotivationFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $ideaIRI = $this->findIriBy(Project::class, ['id' => 1]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);

        $client->request('POST', '/projects', ['json' => [
            'inspiration' => $ideaIRI,
            'process'     => $processIri,
            'progress'    => Project::PROGRESS_CREATING_PROFILE,
            'motivation'  => 'too short',
            'skills'      => 'my project skills',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'motivation: validate.general.tooShort',
        ]);
    }

    public function testCreateProjectWithoutSkillsFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $ideaIRI = $this->findIriBy(Project::class, ['id' => 1]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);

        $client->request('POST', '/projects', ['json' => [
            'inspiration' => $ideaIRI,
            'process'     => $processIri,
            'progress'    => Project::PROGRESS_CREATING_PROFILE,
            'motivation'  => 'my motivation',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'skills: validate.general.notBlank',
        ]);
    }

    public function testCreateProjectWithShortSkillsFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $ideaIRI = $this->findIriBy(Project::class, ['id' => 1]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);

        $client->request('POST', '/projects', ['json' => [
            'inspiration' => $ideaIRI,
            'process'     => $processIri,
            'progress'    => Project::PROGRESS_CREATING_PROFILE,
            'motivation'  => 'my motivation',
            'skills'      => 'my skills',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'skills: validate.general.tooShort',
        ]);
    }

    public function testCreateProjectWithForbiddenProgressFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $ideaIRI = $this->findIriBy(Project::class, ['id' => 1]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);

        $client->request('POST', '/projects', ['json' => [
            'inspiration' => $ideaIRI,
            'process'    => $processIri,
            'progress'   => Project::PROGRESS_CREATING_PLAN,
            'motivation'  => 'my motivation',
            'skills'      => 'my skills',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'progress: The value you selected is not a valid choice.',
        ]);
    }

    public function testStateIsIgnoredWhenCreatingProject(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $ideaIRI = $this->findIriBy(Project::class, ['id' => 1]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);

        $client->request('POST', '/projects', ['json' => [
            'inspiration' => $ideaIRI,
            'process'     => $processIri,
            'progress'    => Project::PROGRESS_CREATING_PROFILE,
            'motivation'  => 'my motivation is good',
            'skills'      => 'my skills are better',
            'state'       => Project::STATE_DEACTIVATED,
        ]]);

        $this->assertResponseIsSuccessful();
        self::assertJsonContains([
            'id'               => 5, // ID 1-4 created by fixtures
            'inspiration'      => [
                'id' => TestFixtures::IDEA['id'],
            ],
            'shortDescription' => TestFixtures::IDEA['shortDescription'],
            'progress'         => Project::PROGRESS_CREATING_PROFILE,
            'state'            => Project::STATE_ACTIVE,
        ]);
    }

    public function testUpdateProject(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $client->request('PUT', $iri, ['json' => [
            'challenges'            => 'new challenges',
            'profileSelfAssessment' => Project::SELF_ASSESSMENT_100_PERCENT,
            'impact'                => [],
            'tasks'                 => null,
            'workPackages'          => null,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'                   => $iri,
            'challenges'            => 'new challenges',
            'description'           => TestFixtures::PROJECT['description'],
            'profileSelfAssessment' => Project::SELF_ASSESSMENT_100_PERCENT,
            'goal'                  => TestFixtures::PROJECT['goal'],
            'impact'                => null
        ]);
    }

    public function testFinishingProfileUpdatesProgress(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        /** @var Project $project */
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $this->assertSame(Project::SELF_ASSESSMENT_75_PERCENT,
            $project->getProfileSelfAssessment());
        $this->assertSame(Project::PROGRESS_CREATING_PROFILE,
            $project->getProgress());

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $client->request('PUT', $iri, ['json' => [
            'profileSelfAssessment' => Project::SELF_ASSESSMENT_100_PERCENT,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'                   => $iri,
            'profileSelfAssessment' => Project::SELF_ASSESSMENT_100_PERCENT,
            'progress'              => Project::PROGRESS_CREATING_PLAN,
        ]);
    }

    public function testUpdateProjectPlan(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $client->request('PUT', $iri, ['json' => [
            'impact'             => ['impact 1', 'impact 2'],
            'outcome'            => ['outcome 1', 'outcome 2'],
            'results'            => ['result 1', 'result 2'],
            'targetGroups'       => ['group 1', 'group 2'],
            'utilization'        => 'We will sell it very soon',
            'planSelfAssessment' => Project::SELF_ASSESSMENT_75_PERCENT,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'                => $iri,
            'impact'             => ['impact 1', 'impact 2'],
            'outcome'            => ['outcome 1', 'outcome 2'],
            'results'            => ['result 1', 'result 2'],
            'targetGroups'       => ['group 1', 'group 2'],
            'utilization'        => 'We will sell it very soon',
            'planSelfAssessment' => Project::SELF_ASSESSMENT_75_PERCENT,
        ]);
    }

    public function testUpdateFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(Project::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'challenges'            => 'new challenges',
            'profileSelfAssessment' => Project::SELF_ASSESSMENT_100_PERCENT,
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
            'email' => TestFixtures::JUROR['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'challenges'            => 'new challenges',
            'profileSelfAssessment' => Project::SELF_ASSESSMENT_100_PERCENT,
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

    public function testUpdateOfIdeaDescriptionIsForbidden(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'shortDescription' => 'changed my mind',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Idea cannot be modified.',
        ]);
    }

    public function testUpdateOfProgressIsIgnored(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $client->request('PUT', $iri, ['json' => [
            'challenges'            => 'new challenges',
            'profileSelfAssessment' => Project::SELF_ASSESSMENT_50_PERCENT,
            'progress'              => Project::PROGRESS_CREATING_APPLICATION,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'                   => $iri,
            'challenges'            => 'new challenges',
            'profileSelfAssessment' => Project::SELF_ASSESSMENT_50_PERCENT,
            'progress'              => Project::PROGRESS_CREATING_PROFILE,
        ]);
    }

    public function testUpdateWithInvalidProfileSelfAssessmentFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'profileSelfAssessment' => 13,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'profileSelfAssessment: The value you selected is not a valid choice.',
        ]);
    }

    public function testUpdateOfStateWithoutPrivilegeIsIgnored(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $client->request('PUT', $iri, ['json' => [
            'state' => Project::STATE_DEACTIVATED,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'   => $iri,
            'state' => Project::STATE_ACTIVE,
        ]);
    }

    public function testUpdateWithEmptyStateIsIgnored(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'challenges' => 'new challenges',
            'state'      => null,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'        => $iri,
            'challenges' => 'new challenges',
            'state'      => Project::STATE_ACTIVE,
        ]);
    }

    public function testUpdateWithInvalidStateFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'state' => '13',
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

    public function testUpdateWithForbiddenStateFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'state' => Project::STATE_INACTIVE,
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

    public function testSettingTasks(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'tasks' => [
                ['id' => '123456', 'description' => 'description'],
                ['id' => '123457', 'description' => 'description'],
            ],
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'   => $iri,
            'tasks' => [
                ['id' => '123456', 'description' => 'description'],
                ['id' => '123457', 'description' => 'description'],
            ],
        ]);
    }

    public function testSettingWorkPackages(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'workPackages' => [
                ['id' => '123456', 'name' => 'name1', 'description' => 'description'],
                ['id' => '123457', 'name' => 'name2', 'description' => 'description'],
            ],
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'   => $iri,
            'workPackages' => [
                ['id' => '123456', 'name' => 'name1', 'description' => 'description'],
                ['id' => '123457', 'name' => 'name2', 'description' => 'description'],
            ],
        ]);
    }

    public function testSettingResources(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'tasks' => [
                ['id' => '123456', 'description' => 'description'],
            ],
            'resources' => [
                [
                    'cost'        => 5,
                    'costType'    => ResourceInput::COST_TYPE_INVESTMENT,
                    'description' => 'short',
                    'id'          => 'abcdef',
                    'source'      => 'ich',
                    'sourceType'  => ResourceInput::SOURCE_TYPE_OWN_FUNDS,
                    'task'        => '123456'
                ],
            ],
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'   => $iri,
            'tasks' => [
                ['id' => '123456', 'description' => 'description'],
            ],
            'resources' => [
                [
                    'cost'        => 5,
                    'costType'    => ResourceInput::COST_TYPE_INVESTMENT,
                    'description' => 'short',
                    'id'          => 'abcdef',
                    'source'      => 'ich',
                    'sourceType'  => ResourceInput::SOURCE_TYPE_OWN_FUNDS,
                    'task'        => '123456'
                ],
            ],
        ]);
    }

    public function testSettingNullTaskFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'tasks' => [null],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'tasks[0].description: validate.general.notBlank'
                ."\ntasks[0].id: validate.general.notBlank",
        ]);
    }

    public function testSettingEmptyTaskFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'tasks' => [[null]],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'tasks[0].description: validate.general.notBlank'
                ."\ntasks[0].id: validate.general.notBlank",
        ]);
    }

    public function testSettingDuplicateTaskIdFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'tasks' => [
                ['description' => 'some text1', 'id' => '123456'],
                ['description' => 'some text2', 'id' => '123456'],
            ],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'tasks: validate.project.duplicateTaskIDs',
        ]);
    }

    public function testSettingEmptyTaskIdFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'tasks' => [
                ['description' => 'some text1', 'id' => null],
                ['description' => 'some text2', 'id' => ''],
            ],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'tasks[0].id: validate.general.notBlank'
                ."\ntasks[1].id: validate.general.notBlank",
        ]);
    }

    public function testSettingTaskWithoutDescriptionFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'tasks' => [
                ['id' => 'abcdef']
            ],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'tasks[0].description: validate.general.notBlank',
        ]);
    }

    public function testSettingInvalidTaskDescriptionFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'tasks' => [
                ['description' => 'abcd', 'id' => '123456'],
                ['description' => null, 'id' => '123457'],
            ],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'tasks[0].description: validate.general.tooShort'
                ."\ntasks[1].description: validate.general.notBlank",
        ]);
    }

    public function testSettingInvalidTaskMonthsFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'tasks' => [
                [
                    'id'          => 'abcdef',
                    'description' => 'testweise',
                    'months'      => ['fail']
                ]
            ],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'tasks[0].months[0]: validate.general.invalidType'
                ."\ntasks[0].months[0]: validate.general.noValidNumber",
        ]);
    }

    public function testSettingNullResourceFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'resources' => [null],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'resources[0].description: validate.general.notBlank'
                ."\nresources[0].id: validate.general.notBlank",
        ]);
    }

    public function testSettingEmptyResourceFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'resources' => [[null]],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'resources[0].description: validate.general.notBlank'
                ."\nresources[0].id: validate.general.notBlank",
        ]);
    }

    public function testSettingResourceWithUnknownTaskIDFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'tasks' => [
                ['description' => 'some text1', 'id' => '123456'],
            ],
            'resources' => [
                ['id' => 'abcdef', 'task' => 'notfound', 'description' => 'some text'],
            ],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'resources: validate.project.resourceWithoutTask',
        ]);
    }

    public function testSettingDuplicateResourceIdFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'tasks' => [
                ['description' => 'some text1', 'id' => '123456'],
            ],
            'resources' => [
                ['id' => 'abcdef', 'task' => '123456', 'description' => 'some text'],
                ['id' => 'abcdef', 'task' => '123456', 'description' => 'other text'],
            ],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'resources: validate.project.duplicateResourceIDs',
        ]);
    }

    public function testSettingEmptyResourceIdFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'tasks' => [
                ['description' => 'some text1', 'id' => 'abcdef'],
            ],
            'resources' => [
                ['id' => null, 'task' => 'abcdef', 'description' => 'description'],
                ['id' => '', 'task' => 'abcdef', 'description' => 'description'],
            ],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'resources[0].id: validate.general.notBlank'
                ."\nresources[1].id: validate.general.notBlank",
        ]);
    }

    public function testSettingResourceWithoutDescriptionFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'tasks' => [
                ['id' => 'abcdef', 'description' => 'description']
            ],
            'resources' => [
                ['id' => '223456', 'task' => 'abcdef'],
            ],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'resources[0].description: validate.general.notBlank',
        ]);
    }

    public function testSettingInvalidResourceDescriptionFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'tasks' => [
                ['description' => 'description', 'id' => '123456'],
            ],
            'resources' => [
                ['description' => 'abcd', 'id' => '223456',
                    'task' => '123456'],
                ['description' => '', 'id' => '323456',
                    'task' => '123456'],
            ],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'resources[0].description: validate.general.tooShort'
                ."\nresources[1].description: validate.general.notBlank",
        ]);
    }

    public function testSettingInvalidResourceCostFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'tasks' => [
                ['id' => 'abcdef', 'description' => 'testweise'],
            ],
            'resources' => [
                ['id' => 'defabc', 'description' => 'testweise',
                    'task' => 'abcdef', 'cost' => -1],
            ]
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'resources[0].cost: validate.general.outOfRange',
        ]);
    }

    public function testSettingNullWorkPackageFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'workPackages' => [null],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'workPackages[0].id: validate.general.notBlank'
                ."\nworkPackages[0].name: validate.general.notBlank",
        ]);
    }

    public function testSettingEmptyWorkPackageFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'workPackages' => [[null]],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'workPackages[0].id: validate.general.notBlank'
                ."\nworkPackages[0].name: validate.general.notBlank",
        ]);
    }

    public function testSettingWorkPackageWithoutIdFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'workPackages' => [
                [
                    'name'        => 'Test',
                    'description' => 'description'
                ]
            ],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'workPackages[0].id: validate.general.notBlank',
        ]);
    }

    public function testSettingWorkPackageWithoutNameFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'workPackages' => [
                [
                    'id'          => '1234567',
                    'description' => 'description'
                ]
            ],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'workPackages[0].name: validate.general.notBlank',
        ]);
    }

    public function testSettingInvalidWorkPackageIdFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'workPackages' => [
                ['name' => 'Test', 'id' => 'a', 'description' => 'description'],
                ['name' => 'Test', 'id' => null, 'description' => 'description'],
            ],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'workPackages[0].id: validate.general.tooShort'
                ."\nworkPackages[1].id: validate.general.notBlank",
        ]);
    }

    public function testSettingDuplicateWorkPackageIdFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'workPackages' => [
                ['name' => 'name1', 'id' => '123456', 'description' => 'description'],
                ['name' => 'name2', 'id' => '123456', 'description' => 'description'],
            ],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'workPackages: validate.project.duplicatePackageIDs',
        ]);
    }

    public function testSettingInvalidWorkPackageNameFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'workPackages' => [
                ['name' => 'T', 'id' => '123456', 'description' => 'description'],
                ['name' => null, 'id' => '123457', 'description' => 'description'],
            ],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'workPackages[0].name: validate.general.tooShort'
                ."\nworkPackages[1].name: validate.general.notBlank",
        ]);
    }

    public function testSettingDuplicateWorkPackageNameFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'workPackages' => [
                ['name' => 'name', 'id' => '123456', 'description' => 'description'],
                ['name' => 'name', 'id' => '123457', 'description' => 'description'],
            ],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'workPackages: validate.project.duplicatePackageNames',
        ]);
    }

    public function testSettingApplicationData(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'contactEmail'      => 'contact@zukunftsstadt.de',
            'contactName'       => 'Kontaktperson',
            'contactPhone'      => '01234-1234512',
            'holderAddressInfo' => 'Im Hinterhaus',
            'holderCity'        => 'Dresden',
            'holderName'        => 'Projektträger',
            'holderStreet'      => 'Waldweg 1',
            'holderZipCode'     => '01234',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'               => $iri,
            'contactEmail'      => 'contact@zukunftsstadt.de',
            'contactName'       => 'Kontaktperson',
            'contactPhone'      => '01234-1234512',
            'holderAddressInfo' => 'Im Hinterhaus',
            'holderCity'        => 'Dresden',
            'holderName'        => 'Projektträger',
            'holderStreet'      => 'Waldweg 1',
            'holderZipCode'     => '01234',
        ]);
    }

    public function testLockingWithoutPrivilegeIsIgnored(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $client->request('PUT', $iri, ['json' => [
            'isLocked' => true,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'      => $iri,
            'id'       => TestFixtures::PROJECT['id'],
        ]);

        $project = static::$container->get('doctrine')
            ->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $this->assertFalse($project->isLocked());
    }

    public function testLockingProject(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'isLocked' => true,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'      => $iri,
            'id'       => 2,
            'isLocked' => true,
        ]);
    }

    public function testDeleteIdeaAsProcessOwner(): void
    {
        $before = new DateTimeImmutable();

        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $iri = $this->findIriBy(Project::class, ['id' => 1]);
        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        /* @var $deleted Project */
        $deleted = static::$container->get('doctrine')
            ->getRepository(Project::class)
            ->find(1);
        $this->assertInstanceOf(Project::class, $deleted);
        $this->assertTrue($deleted->isDeleted());
        $this->assertGreaterThan($before, $deleted->getDeletedAt());
        // @todo delete all non-essential data, test it
    }

    public function testDeleteFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(Project::class, ['id' => 1]);
        $client->request('DELETE', $iri);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testDeleteIdeaFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 1]);
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

    public function testDeleteProjectFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);
        $iri = $this->findIriBy(Project::class, ['id' => 2]);
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
        ])->request('DELETE', '/projects');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "DELETE /projects": Method Not Allowed (Allow: GET, POST)',
        ]);
    }

    // @todo
    // * creating project with duplicate name fails
    // * updating project to duplicate name fails
    // * create and read return the same properties
    // * memberships and applications are shown when reading a project as member
    // * createdBy cannot be set on creation (and also not updated)
    //   while it is annotated with group project:create to be returned after
    //   creation
    // * reading a project as member should not show the memberships/applications etc
    //   of its inspiration
    // * name is unique per process
}
