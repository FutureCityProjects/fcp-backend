<?php
declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use App\PHPUnit\AuthenticatedClientTrait;
use App\PHPUnit\RefreshDatabaseTrait;

/**
 * @group ProjectMembershipApi
 */
class ProjectMembershipApiTest extends ApiTestCase
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

    protected function getOwner()
    {
        return $this->entityManager->getRepository(User::class)
            ->find(TestFixtures::PROJECT_OWNER['id']);
    }

    protected function getMember()
    {
        return $this->entityManager->getRepository(User::class)
            ->find(TestFixtures::PROJECT_MEMBER['id']);
    }

    protected function getProject()
    {
        return $this->entityManager->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
    }

    protected function getLockedProject()
    {
        return $this->entityManager->getRepository(Project::class)
            ->find(TestFixtures::LOCKED_PROJECT['id']);
    }

    protected function getDeletedProject()
    {
        return $this->entityManager->getRepository(Project::class)
            ->find(TestFixtures::DELETED_PROJECT['id']);
    }

    /**
     * Test that no collection of memberships is available, not even for admins.
     */
    public function testCollectionNotAvailable(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ])->request('GET', '/project_memberships');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context' => '/contexts/Error',
            '@type' => 'hydra:Error',
            'hydra:title' => 'An error occurred',
            'hydra:description' => 'No route found for "GET /project_memberships": Method Not Allowed (Allow: POST)',
        ]);
    }

    public function testGetMembershipAsAdmin(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $iri = $this->findIriBy(ProjectMembership::class,
            ['user' => $this->getMember(), 'project' => $this->getProject()]);

        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(ProjectMembership::class);

        self::assertJsonContains([
            '@id'        => $iri,
            'motivation' => 'member motivation',
            'role'       => ProjectMembership::ROLE_MEMBER,
            'skills'     => 'member skills',
            'tasks'      => 'member tasks',
            'project'    => [
                '@id'   => '/projects/2',
                '@type' => 'Project',
                'id'    => 2,
            ],
            'user'       => [
                '@id'   => '/users/6',
                '@type' => 'User',
                'id'    => 6,
            ],
        ]);
    }

    /**
     * Anonymous users cannot get memberships.
     */
    public function testGetMembershipFailsUnauthenticated(): void
    {
        $client = static::createClient();

        $iri = $this->findIriBy(ProjectMembership::class, [
            'user'    => TestFixtures::PROJECT_MEMBER['id'],
            'project' => TestFixtures::PROJECT['id']
        ]);
        $client->request('GET', $iri);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    /**
     * Normal users cannot get memberships, not even their own.
     */
    public function testGetMembershipFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $iri = $this->findIriBy(ProjectMembership::class, [
            'user'    => TestFixtures::PROJECT_MEMBER['id'],
            'project' => TestFixtures::PROJECT['id']
        ]);
        $client->request('GET', $iri);

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

    public function testCreateAsProjectOwner()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::JUROR['id']]);

        $response = $client->request('POST', '/project_memberships', ['json' => [
            'motivation' => 'juror motivation with 20 characters',
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_MEMBER,
            'skills'     => 'juror skills with 20 characters',
            'tasks'      => 'juror tasks',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(ProjectMembership::class);

        self::assertJsonContains([
            'motivation' => 'juror motivation with 20 characters',
            'project'    => [
                '@id' => $projectIRI
            ],
            'role'       => ProjectMembership::ROLE_MEMBER,
            'skills'     => 'juror skills with 20 characters',
            'tasks'      => 'juror tasks',
            'user'       => [
                '@id' => $userIri
            ],
        ]);

        // $data = $response->toArray();
        // @todo should not return the projects full details including other
        // memberships etc
    }

    public function testCreateWithoutRoleFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::JUROR['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'motivation' => 'juror motivation with 20 characters',
            'project'    => $projectIRI,
            'skills'     => 'juror skills with 20 characters',
            'tasks'      => 'juror tasks',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'role: This value should not be null.',
        ]);
    }

    public function testCreateWithUnknownRoleFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::JUROR['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'motivation' => 'juror motivation with 20 characters',
            'project'    => $projectIRI,
            'role'       => 'SUPER_USER',
            'skills'     => 'juror skills with 20 characters',
            'tasks'      => 'juror tasks',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'role: The value you selected is not a valid choice.',
        ]);
    }

    public function testCreateWithoutUserFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'motivation' => 'juror motivation with 20 characters',
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_MEMBER,
            'skills'     => 'juror skills with 20 characters',
            'tasks'      => 'juror tasks',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'user: This value should not be null.',
        ]);
    }

    public function testCreateWithoutMotivationFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::JUROR['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_MEMBER,
            'skills'     => 'juror skills with 20 characters',
            'tasks'      => 'juror tasks',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'motivation: This value should not be null.',
        ]);
    }

    public function testCreateWithoutSkillsFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::JUROR['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'motivation' => 'juror motivation with 20 characters',
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_MEMBER,
            'tasks'      => 'juror tasks',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'skills: This value should not be null.',
        ]);
    }

    public function testCreateWithoutProjectFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::JUROR['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'role'       => ProjectMembership::ROLE_MEMBER,
            'motivation' => 'juror motivation with 20 characters',
            'skills'     => 'juror skills with 20 characters',
            'tasks'      => 'juror tasks',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'project: This value should not be null.',
        ]);
    }

    public function testCreateDuplicateFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::PROJECT_MEMBER['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_MEMBER,
            'motivation' => 'other motivation with 20 characters',
            'skills'     => 'other skills with 20 characters',
            'tasks'      => 'other tasks',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'project: Duplicate membership found.',
        ]);
    }

    public function testCreateApplication()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::JUROR['email']
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::JUROR['id']]);

        $response = $client->request('POST', '/project_memberships', ['json' => [
            'motivation' => 'juror motivation with 20 characters',
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_APPLICANT,
            'skills'     => 'juror skills with 20 characters',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(ProjectMembership::class);

        self::assertJsonContains([
            'motivation' => 'juror motivation with 20 characters',
            'project'    => [
                '@id' => $projectIRI
            ],
            'role'       => ProjectMembership::ROLE_APPLICANT,
            'skills'     => 'juror skills with 20 characters',
            'user'       => [
                '@id' => $userIri
            ],
        ]);

        $data = $response->toArray();
        $this->assertArrayNotHasKey('createdBy', $data['project']);
        $this->assertArrayNotHasKey('memberships', $data['project']);
        $this->assertArrayNotHasKey('applications', $data['project']);
        // @todo should not return the projects full details including other
        // memberships etc
    }

    public function testCreateForOtherUserFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::JUROR['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_APPLICANT,
            'motivation' => 'other motivation with 20 characters',
            'skills'     => 'other skills with 20 characters',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Membership request is not valid.',
        ]);
    }

    public function testCreateForIdeaFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::IDEA['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::PROJECT_MEMBER['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_APPLICANT,
            'motivation' => 'other motivation with 20 characters',
            'skills'     => 'other skills with 20 characters',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Membership request is not valid.',
        ]);
    }

    public function testCreateForDeactivatedProjectFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::JUROR['email']
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $project->setState(Project::STATE_DEACTIVATED);
        $em->flush();
        $em->clear();

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::JUROR['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'motivation' => 'other motivation with 20 characters',
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_APPLICANT,
            'skills'     => 'other skills with 20 characters',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Membership request is not valid.',
        ]);
    }

    public function testCreateForLockedProjectFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::JUROR['email']
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::LOCKED_PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::JUROR['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_APPLICANT,
            'motivation' => 'other motivation',
            'skills'     => 'other skills',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',

            // locked projects are hidden for normal users
            'hydra:description' => 'Item not found for "/projects/3".',
        ]);
    }

    public function testCreateForDeletedProjectFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::DELETED_PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::PROJECT_MEMBER['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_APPLICANT,
            'motivation' => 'other motivation',
            'skills'     => 'other skills',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Item not found for "/projects/4".',
        ]);
    }

    public function testCreateMemberFailsWithoutPrivilege()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::JUROR['email']
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::JUROR['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_MEMBER,
            'motivation' => 'other motivation with 20 characters',
            'skills'     => 'other skills with 20 characters',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Membership request is not valid.',
        ]);
    }

    public function testUpdateAsProcessOwner()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::PROJECT_OWNER['id']]);
        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user' => TestFixtures::PROJECT_OWNER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'       => ProjectMembership::ROLE_OWNER,
            'motivation' => 'new motivation with 20 characters',
            'skills'     => 'new skills with 20 characters',
            'tasks'      => 'new tasks',
        ]]);

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(ProjectMembership::class);

        self::assertJsonContains([
            'motivation' => 'new motivation with 20 characters',
            'project'    => [
                '@id' => $projectIRI
            ],
            'role'       => ProjectMembership::ROLE_OWNER,
            'skills'     => 'new skills with 20 characters',
            'tasks'      => 'new tasks',
            'user'       => [
                '@id' => $userIri
            ],
        ]);
    }

    public function testAcceptApplication()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $juror = $em->getRepository(User::class)
            ->find(TestFixtures::JUROR['id']);
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $ms = new ProjectMembership();
        $ms->setUser($juror);
        $ms->setProject($project);
        $ms->setMotivation('juror motivation');
        $ms->setRole(ProjectMembership::ROLE_APPLICANT);
        $ms->setSkills('juror skills');
        $em->persist($ms);
        $em->flush();
        $em->clear();

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::JUROR['id']]);
        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::JUROR['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'motivation' => 'juror motivation with 20 characters',
            'role'       => ProjectMembership::ROLE_MEMBER,
            'skills'     => 'juror skills with 20 characters',
        ]]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonContains([
            'motivation' => 'juror motivation with 20 characters',
            'project'    => [
                '@id' => $projectIRI
            ],
            'role'       => ProjectMembership::ROLE_MEMBER,
            'skills'     => 'juror skills with 20 characters',
            'tasks'      => null,
            'user'       => [
                '@id' => $userIri
            ],
        ]);
    }

    public function testUpdateAsProjectMember()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::PROJECT_MEMBER['id']]);
        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user' => TestFixtures::PROJECT_MEMBER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'       => ProjectMembership::ROLE_MEMBER,
            'motivation' => 'new motivation with 20 characters',
            'skills'     => 'new skills with 20 characters',
        ]]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonContains([
            'motivation' => 'new motivation with 20 characters',
            'project'    => [
                '@id' => $projectIRI
            ],
            'role'       => ProjectMembership::ROLE_MEMBER,
            'skills'     => 'new skills with 20 characters',
            'tasks'      => 'member tasks',
            'user'       => [
                '@id' => $userIri
            ],
        ]);

        // @todo no creator returned
    }

    public function testUpdateFailsUnauthenticated()
    {
        $client = static::createClient();

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::PROJECT_MEMBER['id']]);
        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user' => TestFixtures::PROJECT_MEMBER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_MEMBER,
            'skills'     => 'new skills',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testUpdateFailsWithoutPrivilege()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::JUROR['email']
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::PROJECT_MEMBER['id']]);
        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user' => TestFixtures::PROJECT_MEMBER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_MEMBER,
            'skills'     => 'new skills',
            'user'       => $userIri,
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

    public function testUpdateOwnRoleFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user' => TestFixtures::PROJECT_MEMBER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'       => ProjectMembership::ROLE_OWNER,
            'motivation' => 'old motivation with 20 characters',
            'skills'     => 'old skills with 20 characters',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Membership update is not valid.',
        ]);
    }

    public function testUpdateProjectFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::LOCKED_PROJECT['id']]);
        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user' => TestFixtures::PROJECT_MEMBER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'       => ProjectMembership::ROLE_MEMBER,
            'skills'     => 'new skills',
            'project'    => $projectIRI,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Extra attributes are not allowed ("project" are unknown).',
        ]);
    }

    public function testUpdateUserFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::JUROR['id']]);
        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user' => TestFixtures::PROJECT_MEMBER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'       => ProjectMembership::ROLE_MEMBER,
            'skills'     => 'new skills',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Extra attributes are not allowed ("user" are unknown).',
        ]);
    }

    public function testUpdateForLockedProjectFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::LOCKED_PROJECT['id'],
            'user' => TestFixtures::PROJECT_MEMBER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'       => ProjectMembership::ROLE_MEMBER,
            'skills'     => 'new skills',
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

    public function testUpdateForDeletedProjectFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        // a deleted project should not have memberships, just to make sure...
        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $project->markDeleted();
        $em->flush();
        $em->clear();

        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::LOCKED_PROJECT['id'],
            'user' => TestFixtures::PROJECT_MEMBER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'       => ProjectMembership::ROLE_MEMBER,
            'skills'     => 'new skills',
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

    public function testUpdateWithEmptyMotivationFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user' => TestFixtures::PROJECT_MEMBER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'       => ProjectMembership::ROLE_MEMBER,
            'motivation' => '',
            'skills'     => 'writing 20 characters long texts',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'motivation: This value is too short.',
        ]);
    }

    public function testUpdateWithEmptySkillsFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_MEMBER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'       => ProjectMembership::ROLE_MEMBER,
            'skills'     => '',
            'motivation' => 'writing 20 characters motivation is cool',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'skills: This value is too short.',
        ]);
    }

    public function testUpdateWithUnknownRoleFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_MEMBER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'       => 'SUPER_USER',
            'skills'     => 'my super good super-hero skills',
            'motivation' => 'writing 20 characters motivation is cool',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'role: The value you selected is not a valid choice.',
        ]);
    }

    public function testDeleteAsProcessOwner()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $iri = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user' => TestFixtures::PROJECT_MEMBER['id'],
        ]);
        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $member = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_MEMBER['id']);
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $membershipExists = $em
            ->getRepository(ProjectMembership::class)
            ->findOneBy(['user' => $member, 'project' => $project]);
        $this->assertInstanceOf(ProjectMembership::class, $membershipExists);;

        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        $notExisting = static::$container->get('doctrine')
            ->getRepository(ProjectMembership::class)
            ->findOneBy(['user' => $member, 'project' => $project]);
        $this->assertNull($notExisting);
    }

    public function testDeleteAsProjectOwner()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);
        $iri = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user' => TestFixtures::PROJECT_MEMBER['id'],
        ]);
        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $member = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_MEMBER['id']);
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $membershipExists = $em
            ->getRepository(ProjectMembership::class)
            ->findOneBy(['user' => $member, 'project' => $project]);
        $this->assertInstanceOf(ProjectMembership::class, $membershipExists);;

        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        $notExisting = static::$container->get('doctrine')
            ->getRepository(ProjectMembership::class)
            ->findOneBy(['user' => $member, 'project' => $project]);
        $this->assertNull($notExisting);
    }

    public function testDeleteAsProjectMember()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);
        $iri = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user' => TestFixtures::PROJECT_MEMBER['id'],
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $member = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_MEMBER['id']);
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $membershipExists = $em
            ->getRepository(ProjectMembership::class)
            ->findOneBy(['user' => $member, 'project' => $project]);
        $this->assertInstanceOf(ProjectMembership::class, $membershipExists);;

        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        $notExisting = static::$container->get('doctrine')
            ->getRepository(ProjectMembership::class)
            ->findOneBy(['user' => $member, 'project' => $project]);
        $this->assertNull($notExisting);
    }

    public function testDeleteAsApplicant()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::JUROR['email']
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $juror = $em->getRepository(User::class)
            ->find(TestFixtures::JUROR['id']);
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);

        $ms = new ProjectMembership();
        $ms->setUser($juror);
        $ms->setProject($project);
        $ms->setMotivation('juror motivation');
        $ms->setRole(ProjectMembership::ROLE_APPLICANT);
        $ms->setSkills('juror skills');
        $em->persist($ms);
        $em->flush();
        $em->clear();

        $iri = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::JUROR['id'],
        ]);
        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        $notExisting = static::$container->get('doctrine')
            ->getRepository(ProjectMembership::class)
            ->findOneBy(['user' => $juror, 'project' => $project]);
        $this->assertNull($notExisting);
    }

    public function testDeleteFailsUnauthenticated()
    {
        $client = static::createClient();
        $iri = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user' => TestFixtures::PROJECT_MEMBER['id'],
        ]);

        $client->request('DELETE', $iri);
        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testDeleteFailsWithoutPrivilege()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::JUROR['email']
        ]);
        $iri = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user' => TestFixtures::PROJECT_MEMBER['id'],
        ]);

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

    public function testDeleteOwnershipFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);
        $iri = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user' => TestFixtures::PROJECT_OWNER['id'],
        ]);

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
        ])->request('DELETE', '/project_memberships');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "DELETE /project_memberships": Method Not Allowed (Allow: POST)',
        ]);
    }
}
