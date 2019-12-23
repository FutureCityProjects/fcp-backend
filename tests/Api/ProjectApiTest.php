<?php
declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\Process;
use App\Entity\Project;
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
        ]);

        $collection = $response->toArray();

        // the locked and the deleted project are NOT returned
        $this->assertCount(2, $collection['hydra:member']);

        $this->assertSame(TestFixtures::IDEA['id'], $collection['hydra:member'][0]['id']);
        $this->assertSame(TestFixtures::PROJECT['id'], $collection['hydra:member'][1]['id']);

        // those properties should not be visible to anonymous
        $this->assertArrayNotHasKey('applications', $collection['hydra:member'][1]);
        $this->assertArrayNotHasKey('createdBy', $collection['hydra:member'][1]);
        $this->assertArrayNotHasKey('isLocked', $collection['hydra:member'][1]);
        $this->assertArrayNotHasKey('memberships', $collection['hydra:member'][1]);
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
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
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
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
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
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
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
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
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

        $response = $client->request('GET', '/projects', [
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
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 2,
        ]);

        $collection = $response->toArray();

        // the deleted project is NOT returned
        $this->assertCount(2, $collection['hydra:member']);
        $this->assertSame(TestFixtures::PROJECT['id'],
            $collection['hydra:member'][0]['id']);
        $this->assertSame(TestFixtures::LOCKED_PROJECT['id'],
            $collection['hydra:member'][1]['id']);
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
            'delimitation'          => null,
            'description'           => null,
            'id'                    => TestFixtures::IDEA['id'],
            'inspiration'           => null,
            'name'                  => null,
            'profileSelfAssessment' => Project::SELF_ASSESSMENT_0_PERCENT,
            'progress'              => Project::PROGRESS_IDEA,
            'shortDescription'      => 'Car-free city center around the year',
            'slug'                  => null,
            'state'                 => Project::STATE_ACTIVE,
            'target'                => null,
            'vision'                => null,
        ]);

        $projectData = $response->toArray();

        // these properties are not public
        $this->assertArrayNotHasKey('createdBy', $projectData);
        $this->assertArrayNotHasKey('isLocked', $projectData);
        $this->assertArrayNotHasKey('memberships', $projectData);
        $this->assertArrayNotHasKey('applications', $projectData);
    }

    public function testGetProject(): void
    {
        $client = static::createClient();

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $response = $client->request('GET', $iri);

        self::assertMatchesResourceItemJsonSchema(Project::class);

        self::assertJsonContains([
            '@id'                   => $iri,
            'challenges'            => 'challenges',
            'delimitation'          => 'delimitation',
            'description'           => 'long description',
            'id'                    => TestFixtures::PROJECT['id'],
            'name'                  => TestFixtures::PROJECT['name'],
            'profileSelfAssessment' => Project::SELF_ASSESSMENT_75_PERCENT,
            'progress'              => Project::PROGRESS_CREATING_PROFILE,
            'shortDescription'      => TestFixtures::PROJECT['shortDescription'],
            'slug'                  => 'car-free-dresden',
            'state'                 => Project::STATE_ACTIVE,
            'target'                => TestFixtures::PROJECT['target'],
            'vision'                => TestFixtures::PROJECT['vision'],
        ]);

        $projectData = $response->toArray();
        $this->assertSame(TestFixtures::IDEA['id'], $projectData['inspiration']['id']);
        $this->assertArrayNotHasKey('createdBy', $projectData);
        $this->assertArrayNotHasKey('isLocked', $projectData);
        $this->assertArrayNotHasKey('memberships', $projectData);
        $this->assertArrayNotHasKey('applications', $projectData);
    }

    public function testGetProjectAsMember(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $response = $client->request('GET', $iri);

        self::assertMatchesResourceItemJsonSchema(Project::class);
        self::assertJsonContains([
            '@id'  => $iri,
            'id'   => TestFixtures::PROJECT['id'],
            'name' => TestFixtures::PROJECT['name'],
        ]);

        $projectData = $response->toArray();
        $this->assertSame(TestFixtures::IDEA['id'], $projectData['inspiration']['id']);
        $this->assertCount(1, $projectData['applications']);
        $this->assertCount(2, $projectData['memberships']);

        // those properties are only visible to the PO/Admin
        $this->assertArrayNotHasKey('createdBy', $projectData);
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
            '@id'  => $iri,
            'id'   => TestFixtures::LOCKED_PROJECT['id'],
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
            '@id'  => $iri,
            'id'   => TestFixtures::LOCKED_PROJECT['id'],
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
            'challenges'            => null,
            'delimitation'          => null,
            'description'           => null,
            'id'                    => 5, // ID 1-4 created by fixtures
            'inspiration'           => null,
            'name'                  => null,
            'progress'              => Project::PROGRESS_IDEA,
            'shortDescription'      => 'just for fun',
            'slug'                  => null,
            'state'                 => Project::STATE_ACTIVE,
            'target'                => null,
            'vision'                => null,
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

        $ideaIRI = $this->findIriBy(Project::class, ['id' => 1]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);

        $response = $client->request('POST', '/projects', ['json' => [
            'inspiration'      => $ideaIRI,
            'name'             => 'New Project #öüäß',
            'process'          => $processIri,
            'progress'         => Project::PROGRESS_CREATING_PROFILE,
            'shortDescription' => 'just for fun',
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(Project::class);

        self::assertJsonContains([
            'challenges'            => null,
            'delimitation'          => null,
            'description'           => null,
            'id'                    => 5, // ID 1-4 created by fixtures
            'inspiration'           => [
                'id'                => 1,
            ],
            'name'                  => "New Project #öüäß",
            'profileSelfAssessment' => Project::SELF_ASSESSMENT_0_PERCENT,
            'progress'              => Project::PROGRESS_CREATING_PROFILE,
            'shortDescription'      => 'just for fun',
            'slug'                  => "new-project-ouass",
            'state'                 => Project::STATE_ACTIVE,
            'target'                => null,
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
            'hydra:description' => 'progress: This value should not be null.',
        ]);
    }

    public function testCreateWithoutProcessFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $ideaIRI = $this->findIriBy(Project::class, ['id' => 1]);

        $client->request('POST', '/projects', ['json' => [
            'inspiration'      => $ideaIRI,
            'name'             => 'no name',
            'shortDescription' => 'no description',
            'progress'         => Project::PROGRESS_CREATING_PROFILE,
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

    public function testCreateWithoutShortDescriptionFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $ideaIRI = $this->findIriBy(Project::class, ['id' => 1]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);

        $client->request('POST', '/projects', ['json' => [
            'name'        => 'no name',
            'inspiration' => $ideaIRI,
            'process'     => $processIri,
            'progress'    => Project::PROGRESS_CREATING_PROFILE,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'shortDescription: This value should not be null.',
        ]);
    }

    public function testCreateProjectWithoutInspirationFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $processIri = $this->findIriBy(Process::class, ['id' => 1]);

        $client->request('POST', '/projects', ['json' => [
            'name'             => 'just for fun',
            'process'          => $processIri,
            'progress'         => Project::PROGRESS_CREATING_PROFILE,
            'shortDescription' => 'The Testers',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'inspiration: Inspiration is required for new projects.',
        ]);
    }

    public function testCreateProjectWithoutNameFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $ideaIRI = $this->findIriBy(Project::class, ['id' => 1]);
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);

        $client->request('POST', '/projects', ['json' => [
            'inspiration'      => $ideaIRI,
            'process'          => $processIri,
            'progress'         => Project::PROGRESS_CREATING_PROFILE,
            'shortDescription' => 'The Testers',
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

    public function testCreateProjectWithForbiddenProgressFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $processIri = $this->findIriBy(Process::class, ['id' => 1]);

        $client->request('POST', '/projects', ['json' => [
            'name'             => 'just for fun',
            'process'          => $processIri,
            'progress'         => Project::PROGRESS_CREATING_PLAN,
            'shortDescription' => 'The Testers',
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
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'                   => $iri,
            'challenges'            => 'new challenges',
            'description'           => TestFixtures::PROJECT['description'],
            'profileSelfAssessment' => Project::SELF_ASSESSMENT_100_PERCENT,
            'target'                => TestFixtures::PROJECT['target'],
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

    public function testUpdateOfProgressIsForbidden(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'challenges'            => 'new challenges',
            'profileSelfAssessment' => Project::SELF_ASSESSMENT_100_PERCENT,
            'progress'              => Project::PROGRESS_CREATING_APPLICATION,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Extra attributes are not allowed ("progress" are unknown).',
        ]);
    }

    public function testUpdateWithEmptyNameFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'name'       => '',
            'challenges' => 'new challenges',
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

    public function testUpdateOfStateFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'state'              => Project::STATE_DEACTIVATED,
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

    public function testLockingFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'isLocked' => true,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Extra attributes are not allowed ("isLocked" are unknown).',
        ]);
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

    // @todo
    // * create and read return the same properties
    // * memberships and applications are shown when reading a project as member
    // * createdBy cannot be set on creation (and also not updated)
    //   while it is annotated with group project:create to be returned after
    //   creation
}   // * reading a project as member should not show the memberships/applications etc
    //   of its inspiration
    // * name is unique per process
