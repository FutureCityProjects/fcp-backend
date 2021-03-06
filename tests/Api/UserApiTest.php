<?php
declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\Fund;
use App\Entity\Process;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use App\Entity\UserObjectRole;
use App\Message\UserEmailChangeMessage;
use App\Message\UserForgotPasswordMessage;
use App\Message\UserRegisteredMessage;
use App\PHPUnit\AuthenticatedClientTrait;
use App\PHPUnit\RefreshDatabaseTrait;
use DateTimeImmutable;

/**
 * @group UserApi
 */
class UserApiTest extends ApiTestCase
{
    use AuthenticatedClientTrait;
    use RefreshDatabaseTrait;

    public function testGetCollection(): void
    {
        $response = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ])->request('GET', '/users');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceCollectionJsonSchema(User::class);

        self::assertJsonContains([
            '@context'         => '/contexts/User',
            '@id'              => '/users',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 5,
        ]);

        $collection = $response->toArray();

        $this->assertCount(5, $collection['hydra:member']);

        $ids = [];
        foreach ($collection['hydra:member'] as $user) {
            $ids[] = $user['id'];
        }
        $this->assertContains(TestFixtures::ADMIN['id'], $ids);
        $this->assertContains(TestFixtures::PROCESS_OWNER['id'], $ids);
        $this->assertContains(TestFixtures::PROJECT_MEMBER['id'], $ids);
        $this->assertContains(TestFixtures::PROJECT_OWNER['id'], $ids);
        $this->assertNotContains(TestFixtures::DELETED_USER['id'], $ids);
    }

    public function testGetCollectionFailsUnauthenticated(): void
    {
        static::createClient()->request('GET', '/users');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testGetCollectionFailsWithoutPrivilege(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ])->request('GET', '/users');

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
     * Filter the collection by exact username -> one result
     */
    public function testGetUsersByUsername(): void
    {
        $response = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ])->request('GET', '/users', ['query' => [
            'username' => TestFixtures::PROJECT_OWNER['username']
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context'         => '/contexts/User',
            '@id'              => '/users',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
        ]);

        $result = $response->toArray();
        $this->assertCount(1, $result['hydra:member']);
        $this->assertSame(TestFixtures::PROJECT_OWNER['email'],
            $result['hydra:member'][0]['email']);
    }

    /**
     * Filter the collection for undeleted users only, same as default.
     */
    public function testGetUndeletedUsers(): void
    {
        $response = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ])->request('GET', '/users', ['query' => ['exists[deletedAt]' => 0]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context'         => '/contexts/User',
            '@id'              => '/users',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 5,
        ]);

        $this->assertCount(5, $response->toArray()['hydra:member']);
    }

    /**
     * Admins can explicitly request deleted users via filter.
     */
    public function testGetDeletedUsersAsAdmin(): void
    {
        $response = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ])->request('GET', '/users', ['query' => ['exists[deletedAt]' => 1]]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceCollectionJsonSchema(User::class);

        self::assertJsonContains([
            '@context'         => '/contexts/User',
            '@id'              => '/users',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
        ]);

        $collection = $response->toArray();

        $this->assertCount(1, $collection['hydra:member']);
        $this->assertSame(TestFixtures::DELETED_USER['id'],
            $collection['hydra:member'][0]['id']);
    }

    /**
     * Process owners cannot get deleted users, the collection must be empty.
     */
    public function testGetDeletedUsersAsProcessOwner(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ])->request('GET', '/users', ['query' => ['exists[deletedAt]' => 1]]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceCollectionJsonSchema(User::class);

        self::assertJsonContains([
            '@context'         => '/contexts/User',
            '@id'              => '/users',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 0,
        ]);
    }

    public function testGet(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_MEMBER['email']]);
        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(User::class);

        self::assertJsonContains([
            '@id'                => $iri,
            'createdAt'          => '2019-02-01T23:00:00+00:00',
            'username'           => TestFixtures::PROJECT_MEMBER['username'],
            'email'              => TestFixtures::PROJECT_MEMBER['email'],
            'id'                 => TestFixtures::PROJECT_MEMBER['id'],
            'isActive'           => true,
            'isValidated'        => true,
            'objectRoles'        => [],
            'roles'              => [User::ROLE_USER],
            'projectMemberships' => [
                0 => [
                    '@id'        => '/project_memberships/project=2;user=6',
                    '@type'      => 'ProjectMembership',
                    'motivation' => 'member motivation',
                    'role'       => 'member',
                    'skills'     => 'member skills',
                    'tasks'      => 'member tasks',
                    'project'    => [
                        '@id'   => '/projects/2',
                        '@type' => 'Project',
                        'id'    => 2,
                        'name'  => 'Car-free Dresden',
                    ],
                ],
                1 => [
                    '@id'        => '/project_memberships/project=3;user=6',
                    '@type'      => 'ProjectMembership',
                    'motivation' => 'member motivation',
                    'role'       => 'member',
                    'skills'     => 'member skills',
                    'tasks'      => 'member tasks',
                    'project'    => [
                        '@id'   => '/projects/3',
                        '@type' => 'Project',
                        'id'    => 3,
                        'name'  => 'Locked Project',
                    ],
                ],
            ],
        ]);
    }

    public function testGetSelf(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::JUROR['email']
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::JUROR['email']]);
        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(User::class);

        self::assertJsonContains([
            '@id'                => $iri,
            'createdAt'          => '2018-12-31T23:00:00+00:00',
            'username'           => TestFixtures::JUROR['username'],
            'email'              => TestFixtures::JUROR['email'],
            'id'                 => TestFixtures::JUROR['id'],
            'isActive'           => true,
            'isValidated'        => true,
            'objectRoles'        => [
                0 => [
                    'objectId'   => 1,
                    'objectType' => Fund::class,
                    'role'       => UserObjectRole::ROLE_JURY_MEMBER
                ],
            ],
            'roles'              => [User::ROLE_USER],
            'projectMemberships' => [],
        ]);
    }

    public function testGetSelfWithMemberships(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_MEMBER['email']]);
        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(User::class);

        self::assertJsonContains([
            '@id'                => $iri,
            'username'           => TestFixtures::PROJECT_MEMBER['username'],
            'email'              => TestFixtures::PROJECT_MEMBER['email'],
            'id'                 => TestFixtures::PROJECT_MEMBER['id'],
            'roles'              => [User::ROLE_USER],
            'projectMemberships' => [
                0 => [
                    '@type'      => 'ProjectMembership',
                    'motivation' => 'member motivation',
                    'project'    => [
                        'id' => TestFixtures::PROJECT['id'],
                    ],
                    'role'       => ProjectMembership::ROLE_MEMBER,
                    'skills'     => 'member skills',
                    'tasks'      => 'member tasks',
                ],
                1 => [
                    '@type'      => 'ProjectMembership',
                    'motivation' => 'member motivation',
                    'project'    => [
                        'id' => TestFixtures::LOCKED_PROJECT['id'],
                    ],
                    'role'       => ProjectMembership::ROLE_MEMBER,
                    'skills'     => 'member skills',
                    'tasks'      => 'member tasks',
                ],
            ],
        ]);
    }

    public function testGetFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_MEMBER['email']]);

        $client->request('GET', $iri);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testGetFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_OWNER['email']]);

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

    /**
     * Admins can request deleted users.
     */
    public function testGetDeletedUserAsAdmin(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::DELETED_USER['email']]);

        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(User::class);
    }

    /**
     * Process owners cannot get a deleted user, returns 404.
     */
    public function testGetDeletedUserAsProcessOwner(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::DELETED_USER['email']]);

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

    public function testCreate(): void
    {
        $response = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ])->request('POST', '/users', ['json' => [
            'username' => 'Tester',
            'email'    => 'new@zukunftsstadt.de',
            'password' => 'irrelevant',
            'roles'    => [],
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(User::class);

        self::assertJsonContains([
            '@context'    => '/contexts/User',
            '@type'       => 'User',
            'email'       => 'new@zukunftsstadt.de',
            'username'    => 'Tester',
            'isActive'    => true,
            'isValidated' => false,
            'firstName'   => null,
            'lastName'    => null,
            'roles'       => [User::ROLE_USER],
            'objectRoles' => [],
            'projectMemberships' => [],
        ]);

        $userData = $response->toArray();
        $this->assertRegExp('~^/users/\d+$~', $userData['@id']);
        $this->assertArrayHasKey('id', $userData);
        $this->assertIsInt($userData['id']);
        $this->assertArrayNotHasKey('password', $userData);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)->find($userData['id']);

        // user has a password and it was encoded
        $this->assertNotEmpty($user->getPassword());
        $this->assertNotSame('irrelevant', $user->getPassword());
    }

    public function testCreateFailsUnauthenticated(): void
    {
        static::createClient()->request('POST', '/users', ['json' => [
            'email'    => 'new@zukunftsstadt.de',
            'username' => 'Tester',
            'password' => 'irrelevant',
            'roles'    => [],
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
            'email'    => 'new@zukunftsstadt.de',
            'username' => 'Tester',
            'password' => 'irrelevant',
            'roles'    => [],
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

    public function testCreateOverwritingDefault(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ])->request('POST', '/users', ['json' => [
            'email'       => 'new@zukunftsstadt.de',
            'username'    => 'Tester',
            'password'    => 'irrelevant',
            'roles'       => [User::ROLE_ADMIN],
            'isActive'    => false,
            'isValidated' => true,
            'firstName'   => 'Peter',
            'lastName'    => 'Lustig',
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(User::class);

        self::assertJsonContains([
            'isActive'    => false,
            'isValidated' => true,
            'roles'       => [User::ROLE_ADMIN, User::ROLE_USER],
            'firstName'   => 'Peter',
            'lastName'    => 'Lustig',
        ]);
    }

    public function testCreateWithoutEmailFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ])->request('POST', '/users', ['json' => [
            'username' => 'Tester',
            'password' => 'invalid',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'email: validate.general.notBlank',
        ]);
    }

    public function testCreateWithoutUsernameFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ])->request('POST', '/users', ['json' => [
            'email'    => 'test@zukunftsstadt.de',
            'password' => 'invalid',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'username: validate.general.notBlank',
        ]);
    }

    public function testCreateWithDuplicateUsernameFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ])->request('POST', '/users', ['json' => [
            'email'    => 'test@zukunftsstadt.de',
            'username' => TestFixtures::ADMIN['username'],
            'password' => 'invalid',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'username: Username already exists.',
        ]);
    }

    public function testCreateWithDuplicateEmailFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ])->request('POST', '/users', ['json' => [
            'email'    => TestFixtures::ADMIN['email'],
            'username' => 'Tester',
            'password' => 'invalid',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'email: Email already exists.',
        ]);
    }

    public function testCreateWithInvalidEmailFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ])->request('POST', '/users', ['json' => [
            'username' => 'invalid-mail-user',
            'email'    => 'no-email',
            'password' => 'invalid',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'email: This value is not a valid email address.',
        ]);
    }

    public function testCreateWithInvalidRolesFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ])->request('POST', '/users', ['json' => [
            'email'    => 'new@zukunftsstadt.de',
            'password' => 'invalid',
            'roles'    => User::ROLE_ADMIN, // should be an array to work
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'The type of the "roles" attribute for class'
             .' "App\\Dto\\UserInput" must be one of "array" ("string" given).',
        ]);
    }

    public function testCreateWithUnknownRoleFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ])->request('POST', '/users', ['json' => [
            'email'    => 'new@zukunftsstadt.de',
            'password' => 'irrelevant',
            'roles'    => ['SUPER_USER'],
            'username' => 'will-fail'
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'roles[0]: The value you selected is not a valid choice.',
        ]);
    }

    public function testCreateWithoutPasswordSetsRandom(): void
    {
        $response = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ])->request('POST', '/users', ['json' => [
            'email'    => 'new@zukunftsstadt.de',
            'username' => 'tester',
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(User::class);

        $userData = $response->toArray();
        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)->find($userData['id']);
        $this->assertNotEmpty($user->getPassword());
    }

    public function testRegistration(): void
    {
        $response = static::createClient()
            ->request('POST', '/users/register', ['json' => [
                'username'      => 'Tester',
                'email'         => 'new@zukunftsstadt.de',
                'firstName'     => 'Peter',
                'password'      => 'irrelevant',
                'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(User::class);

        self::assertJsonContains([
            '@context'    => '/contexts/User',
            '@type'       => 'User',
            'email'       => 'new@zukunftsstadt.de',
            'username'    => 'Tester',
            'isActive'    => true,
            'isValidated' => false,
            'firstName'   => 'Peter',
            'lastName'    => null,
            'roles'       => [User::ROLE_USER],
            'objectRoles' => [],
            'projectMemberships' => [],
        ]);

        $userData = $response->toArray();
        $this->assertArrayNotHasKey('password', $userData);

        $messenger = self::$container->get('messenger.default_bus');
        $messages = $messenger->getDispatchedMessages();
        $this->assertCount(1, $messages);
        $this->assertInstanceOf(UserRegisteredMessage::class,
            $messages[0]['message']);
    }

    public function testRegistrationWithIdea(): void
    {
        $client = static::createClient();
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);

        $client->request('POST', '/users/register', ['json' => [
                'username'      => 'Tester',
                'email'         => 'new@zukunftsstadt.de',
                'firstName'     => 'Peter',
                'password'      => 'irrelevant',
                'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
                'createdProjects' => [
                    [
                        'shortDescription' => 'this is a idea from a new user',
                        'process'          => $processIri,
                        'progress'         => Project::PROGRESS_IDEA
                    ]
                ]
            ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(User::class);

        self::assertJsonContains([
            '@context'    => '/contexts/User',
            '@type'       => 'User',
            'email'       => 'new@zukunftsstadt.de',
            'username'    => 'Tester',
            'isActive'    => true,
            'isValidated' => false,
            'firstName'   => 'Peter',
            'lastName'    => null,
            'roles'       => [User::ROLE_USER],
            'objectRoles' => [],
            'projectMemberships' => [],
            'createdProjects'    => [
                [
                    '@type'            => 'Project',
                    'shortDescription' => 'this is a idea from a new user',
                    'process'          => [
                        '@id'   => '/processes/1',
                        '@type' => 'Process',
                        'id'    => 1,
                    ],
                    'progress'         => Project::PROGRESS_IDEA
                ],
            ],
        ]);
    }

    public function testRegistrationWithProject(): void
    {
        $client = static::createClient();
        $processIri = $this->findIriBy(Process::class, ['id' => 1]);
        $ideaIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::IDEA['id']]);

        $client->request('POST', '/users/register', ['json' => [
            'username'      => 'Tester',
            'email'         => 'new@zukunftsstadt.de',
            'firstName'     => 'Peter',
            'password'      => 'irrelevant',
            'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            'createdProjects' => [
                [
                    'inspiration' => $ideaIri,
                    'motivation' => 'I wanna do something',
                    'process'    => $processIri,
                    'progress'   => Project::PROGRESS_CREATING_PROFILE,
                    'skills'     => 'I can do it',
                ],
            ],
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(User::class);

        self::assertJsonContains([
            '@context'           => '/contexts/User',
            '@type'              => 'User',
            'projectMemberships' => [
                [
                    '@type'      => 'ProjectMembership',
                    'motivation' => 'I wanna do something',
                    'role'       => ProjectMembership::ROLE_OWNER,
                    'skills'     => 'I can do it',
                ],
            ],
            'createdProjects'    => [
                [
                    '@type'            => 'Project',
                    'inspiration'      => [
                        '@type' => 'Project',
                        'id'    => TestFixtures::IDEA['id'],
                    ],
                    'shortDescription' => TestFixtures::IDEA['shortDescription'],
                    'progress'         => Project::PROGRESS_CREATING_PROFILE,
                    'process'          => [
                        '@id'   => '/processes/1',
                        '@type' => 'Process',
                        'id'    => 1,
                    ],
                ],
            ],
        ]);
    }

    public function testRegistrationWithDuplicateEmailFails(): void
    {
        static::createClient()->request('POST', '/users/register', ['json' => [
            'email'         => TestFixtures::ADMIN['email'],
            'username'      => 'Tester',
            'password'      => 'invalid',
            'validationUrl' => 'https://fcp.de/?token={{token}}&id={{id}}',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'email: Email already exists.',
        ]);
    }

    public function testRegistrationWithoutValidationUrlFails(): void
    {
        static::createClient()->request('POST', '/users/register', ['json' => [
            'email'         => 'new@zukunftsstadt.de',
            'username'      => 'Tester',
            'password'      => 'invalid',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validationUrl: validate.general.notBlank',
        ]);
    }

    public function testRegistrationWithoutIdPlaceholderFails(): void
    {
        static::createClient()->request('POST', '/users/register', ['json' => [
            'email'         => 'new@zukunftsstadt.de',
            'username'      => 'Tester',
            'password'      => 'invalid',
            'validationUrl' => 'http://fcp.de/?token={{token}}'
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validationUrl: ID placeholder is missing.',
        ]);
    }

    public function testRegistrationWithoutTokenPlaceholderFails(): void
    {
        static::createClient()->request('POST', '/users/register', ['json' => [
            'email'         => 'new@zukunftsstadt.de',
            'username'      => 'Tester',
            'password'      => 'invalid',
            'validationUrl' => 'https://fcp.de/?token=token&id={{id}}'
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validationUrl: Token placeholder is missing.',
        ]);
    }

    public function testRegistrationWithInvalidUsernameFails(): void
    {
        static::createClient()->request('POST', '/users/register', ['json' => [
            'email'         => 'test@zukunftsstadt.de',
            'password'      => 'invalid',
            'username'      => '1@2',
            'validationUrl' => 'https://fcp.de/?token={{token}}&id={{id}}'
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'username: validate.user.username.notValid',
        ]);
    }

    public function testRegistrationWithApplication(): void
    {
        $client = static::createClient();
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $client->request('POST', '/users/register', ['json' => [
            'username'      => 'Tester',
            'email'         => 'new@zukunftsstadt.de',
            'firstName'     => 'Peter',
            'password'      => 'irrelevant',
            'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            'projectMemberships' => [
                [
                    'motivation' => 'I wanna do something',
                    'project'    => $projectIri,
                    'role'       => ProjectMembership::ROLE_APPLICANT,
                    'skills'     => 'I can do it',
                ],
            ],
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(User::class);

        self::assertJsonContains([
            '@context'           => '/contexts/User',
            '@type'              => 'User',
            'username'           => 'Tester',
            'email'              => 'new@zukunftsstadt.de',
            'firstName'          => 'Peter',
            'projectMemberships' => [
                [
                    '@type'      => 'ProjectMembership',
                    'motivation' => 'I wanna do something',
                    'project'    => [
                        '@id' => $projectIri,
                    ],
                    'role'       => ProjectMembership::ROLE_APPLICANT,
                    'skills'     => 'I can do it',
                ],
            ],
        ]);
    }

    public function testRegistrationWithApplicationFailsWithForbiddenRole(): void
    {
        $client = static::createClient();
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $client->request('POST', '/users/register', ['json' => [
            'username'      => 'Tester',
            'email'         => 'new@zukunftsstadt.de',
            'firstName'     => 'Peter',
            'password'      => 'irrelevant',
            'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            'projectMemberships' => [
                [
                    'motivation' => 'I wanna do something',
                    'project'    => $projectIri,
                    'role'       => ProjectMembership::ROLE_OWNER,
                    'skills'     => 'I can do it',
                ],
            ],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validate.projectMembership.invalidRequest',
        ]);
    }

    public function testRegistrationWithApplicationFailsForIdea(): void
    {
        $client = static::createClient();
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::IDEA['id']]);

        $client->request('POST', '/users/register', ['json' => [
            'username'      => 'Tester',
            'email'         => 'new@zukunftsstadt.de',
            'firstName'     => 'Peter',
            'password'      => 'irrelevant',
            'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            'projectMemberships' => [
                [
                    'motivation' => 'I wanna do something',
                    'project'    => $projectIri,
                    'role'       => ProjectMembership::ROLE_APPLICANT,
                    'skills'     => 'I can do it',
                ],
            ],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validate.projectMembership.invalidRequest',
        ]);
    }

    public function testRegistrationWithApplicationFailsForLockedProject(): void
    {
        $client = static::createClient();
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::LOCKED_PROJECT['id']]);

        $client->request('POST', '/users/register', ['json' => [
            'username'      => 'Tester',
            'email'         => 'new@zukunftsstadt.de',
            'firstName'     => 'Peter',
            'password'      => 'irrelevant',
            'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            'projectMemberships' => [
                [
                    'motivation' => 'I wanna do something',
                    'project'    => $projectIri,
                    'role'       => ProjectMembership::ROLE_APPLICANT,
                    'skills'     => 'I can do it',
                ],
            ],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Item not found for "/projects/3".',
        ]);
    }

    public function testRegistrationWithApplicationFailsForDeletedProject(): void
    {
        $client = static::createClient();
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::DELETED_PROJECT['id']]);

        $client->request('POST', '/users/register', ['json' => [
            'username'      => 'Tester',
            'email'         => 'new@zukunftsstadt.de',
            'firstName'     => 'Peter',
            'password'      => 'irrelevant',
            'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            'projectMemberships' => [
                [
                    'motivation' => 'I wanna do something',
                    'project'    => $projectIri,
                    'role'       => ProjectMembership::ROLE_APPLICANT,
                    'skills'     => 'I can do it',
                ],
            ],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Item not found for "/projects/4".',
        ]);
    }

    public function testUpdate(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $before = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_MEMBER['id']);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_MEMBER['email']]);
        $client->request('PUT', $iri, ['json' => [
            'email'       => TestFixtures::PROJECT_MEMBER['email'],
            'isActive'    => false,
            'isValidated' => false,
            'roles'       => [User::ROLE_ADMIN],
            'firstName'   => 'Erich',
            'lastName'    => 'Müller',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'         => $iri,
            'isActive'    => false,
            'isValidated' => false,
            'roles'       => [User::ROLE_ADMIN, User::ROLE_USER],
            'firstName'   => 'Erich',
            'lastName'    => 'Müller',
        ]);

        $em->clear();
        $after = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_MEMBER['id']);

        // password stays unchanged
        $this->assertSame($before->getPassword(), $after->getPassword());
    }

    public function testUpdateSelf(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_MEMBER['email']]);
        $client->request('PUT', $iri, ['json' => [
            'firstName' => 'Erich',
            'lastName'  => 'Müller',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'       => $iri,
            'firstName' => 'Erich',
            'lastName'  => 'Müller',
        ]);
    }

    public function testUpdateFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_MEMBER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'email'    => TestFixtures::PROJECT_MEMBER['email'],
            'username' => TestFixtures::PROJECT_MEMBER['username'],
            'isActive' => false,
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testUpdateFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_OWNER['email']]);

        $r = $client->request('PUT', $iri, ['json' => [
            'email'    => TestFixtures::PROJECT_MEMBER['email'],
            'username' => TestFixtures::PROJECT_MEMBER['username'],
            'isActive' => false,
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

    public function testUpdatePassword(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $oldPW = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_MEMBER['id'])
            ->getPassword();

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_MEMBER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'email'       => TestFixtures::PROJECT_MEMBER['email'],
            'password'    => 'new-passw0rd'
        ]]);

        self::assertResponseIsSuccessful();

        $em->clear();
        $after = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_MEMBER['id']);

        // password changed and is encoded
        $this->assertNotSame($oldPW, $after->getPassword());
        $this->assertNotSame('new-passw0rd', $after->getPassword());
    }

    public function testUpdateEmail(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_MEMBER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'email' => 'new@zukunftsstadt.de',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'         => $iri,
            'email'       => 'new@zukunftsstadt.de',
        ]);
    }

    public function testUpdateWithDuplicateEmailFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_MEMBER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'email'       => TestFixtures::ADMIN['email'],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'email: Email already exists.',
        ]);
    }

    public function testUpdateWithInvalidEmailFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_MEMBER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'email'       => 'no-email',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'email: This value is not a valid email address.',
        ]);
    }

    public function testUpdateWithDuplicateUsernameFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_MEMBER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'username'       => TestFixtures::ADMIN['username'],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'username: Username already exists.',
        ]);
    }

    public function testUpdateWithEmptyUsernameFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_MEMBER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'username' => '',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'username: validate.general.notBlank',
        ]);
    }

    public function testUpdateWithUnknownRoleFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_MEMBER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'email' => 'test@example.com',
            'roles' => ['SUPER_USER'],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'roles[0]: The value you selected is not a valid choice.',
        ]);
    }

    public function testUpdateOwnEmailFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_MEMBER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'email' => 'new@zukunftsstadt.de',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'   => $iri,
            'email' => TestFixtures::PROJECT_MEMBER['email'],
        ]);
    }

    public function testUpdateOfOwnUsernameIsIgnored(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_MEMBER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'username' => 'new-name',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'         => $iri,
            'username'    => TestFixtures::PROJECT_MEMBER['username'],
        ]);
    }

    public function testUpdateOfOwnRolesIsIgnored(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_MEMBER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'roles' => [User::ROLE_ADMIN],
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'         => $iri,
            'roles'       => [User::ROLE_USER],
        ]);
    }

    public function testUpdateOfOwnIsActiveIsIgnored(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_MEMBER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'isActive' => false,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'         => $iri,
            'isActive'    => true,
        ]);
    }

    public function testUpdateOfOwnIsValidatedIsIgnored(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_MEMBER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'isValidated' => false,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'         => $iri,
            'isValidated' => true,
        ]);
    }

    public function testDelete(): void
    {
        $before = new DateTimeImmutable();

        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_MEMBER['email']]);

        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        /** @var User $user */
        $user = static::$container->get('doctrine')
            ->getRepository(User::class)
            ->find(TestFixtures::PROJECT_MEMBER['id']);
        $this->assertNotNull($user);
        $this->assertTrue($user->isDeleted());
        $this->assertGreaterThan($before, $user->getDeletedAt());
        $this->assertCount(0, $user->getProjectMemberships());
        // removal of other private data is tested in Enity\UserTest
    }

    public function testDeleteSelf(): void
    {
        $before = new DateTimeImmutable();

        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_MEMBER['email']]);

        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        /** @var User $user */
        $user = static::$container->get('doctrine')
            ->getRepository(User::class)
            ->find(TestFixtures::PROJECT_MEMBER['id']);
        $this->assertNotNull($user);
        $this->assertTrue($user->isDeleted());
        $this->assertGreaterThan($before, $user->getDeletedAt());
        $this->assertCount(0, $user->getProjectMemberships());
        // removal of other private data is tested in Enity\UserTest
    }

    public function testDeleteFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_MEMBER['email']]);

        $client->request('DELETE', $iri);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type', 'application/json');

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

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_OWNER['email']]);

        $client->request('DELETE', $iri);

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Access Denied.',
        ]);
    }

    public function testDeleteDeletedUserFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::DELETED_USER['email']]);

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
        ])->request('DELETE', '/users');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "DELETE /users": Method Not Allowed (Allow: GET, POST)',
        ]);
    }

    public function testPasswordResetWithUsername(): void
    {
        static::createClient()
            ->request('POST', '/users/reset-password', ['json' => [
                'username'      => TestFixtures::PROJECT_OWNER['username'],
                'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            ]]);

        self::assertResponseStatusCodeSame(202);
        self::assertJsonContains([
            'success' => true,
            'message' => 'Request received',
        ]);

        $messenger = self::$container->get('messenger.default_bus');
        $messages = $messenger->getDispatchedMessages();
        $this->assertCount(1, $messages);
        $this->assertInstanceOf(UserForgotPasswordMessage::class,
            $messages[0]['message']);
        $this->assertSame(TestFixtures::PROJECT_OWNER['id'],
            $messages[0]['message']->userId);
    }

    public function testPasswordResetWithEmail(): void
    {
        static::createClient()
            ->request('POST', '/users/reset-password', ['json' => [
                'username'      => TestFixtures::PROJECT_OWNER['email'],
                'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            ]]);

        self::assertResponseStatusCodeSame(202);
        self::assertJsonContains([
            'success' => true,
            'message' => 'Request received',
        ]);

        $messenger = self::$container->get('messenger.default_bus');
        $messages = $messenger->getDispatchedMessages();
        $this->assertCount(1, $messages);
        $this->assertInstanceOf(UserForgotPasswordMessage::class,
            $messages[0]['message']);
        $this->assertSame(TestFixtures::PROJECT_OWNER['id'],
            $messages[0]['message']->userId);
    }

    public function testPasswordResetWithUnknownUsernameFails(): void
    {
        static::createClient()
            ->request('POST', '/users/reset-password', ['json' => [
                'username'      => 'does-not-exist',
                'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            ]]);

        self::assertResponseStatusCodeSame(404);
        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No matching user found.',
        ]);
    }

    public function testPasswordResetWithUnknownEmailFails(): void
    {
        static::createClient()
            ->request('POST', '/users/reset-password', ['json' => [
                'username'      => 'does@not-exist.de',
                'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            ]]);

        self::assertResponseStatusCodeSame(404);
        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No matching user found.',
        ]);
    }

    public function testPasswordResetWithEmptyUsernameFails(): void
    {
        static::createClient()
            ->request('POST', '/users/reset-password', ['json' => [
                'username'      => '',
                'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'username: validate.general.notBlank',
        ]);
    }

    public function testPasswordResetWithoutValidationUrlFails(): void
    {
        static::createClient()
            ->request('POST', '/users/reset-password', ['json' => [
                'username' => 'irrelevant',
            ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validationUrl: validate.general.notBlank',
        ]);
    }

    public function testPasswordResetWithoutIdPlaceholderFails(): void
    {
        static::createClient()
            ->request('POST', '/users/reset-password', ['json' => [
                'username'      => 'irrelevant',
                'validationUrl' => 'http://fcp.de/?token={{token}}'
            ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validationUrl: ID placeholder is missing.',
        ]);
    }

    public function testPasswordResetWithoutTokenPlaceholderFails(): void
    {
        static::createClient()
            ->request('POST', '/users/reset-password', ['json' => [
                'username' => 'irrelevant',
                'validationUrl' => 'http://fcp.de/?id={{id}}'
            ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validationUrl: Token placeholder is missing.',
        ]);
    }

    public function testPasswordResetFailsAuthenticated(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);
        $client->request('POST', '/users/reset-password', ['json' => [
            'username'     => 'irrelevant',
            'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
        ]]);

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Forbidden for authenticated users.',
        ]);
    }

    public function testPasswordResetWithDeletedUserFails(): void
    {
        static::createClient()
            ->request('POST', '/users/reset-password', ['json' => [
                'username'      => TestFixtures::DELETED_USER['email'],
                'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            ]]);

        self::assertResponseStatusCodeSame(404);
        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No matching user found.',
        ]);
    }

    public function testPasswordResetWithInactiveUserFails(): void
    {
        $client = static::createClient();
        $em = static::$kernel->getContainer()->get('doctrine')->getManager();

        $user = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_OWNER['id']);
        $user->setIsActive(false);
        $em->flush();
        $em->clear();

        $client->request('POST', '/users/reset-password', ['json' => [
            'username'      => TestFixtures::PROJECT_OWNER['email'],
            'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
        ]]);

        self::assertResponseStatusCodeSame(404);
        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No matching user found.',
        ]);
    }

    public function testEmailChange(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROCESS_OWNER['id']]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROCESS_OWNER['email']]);

        $client->request('POST', $iri.'/change-email', ['json' => [
            'email'         => 'new@zukunftsstadt.de',
            'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
        ]]);

        self::assertResponseStatusCodeSame(202);
        self::assertJsonContains([
            'success' => true,
            'message' => 'Request received',
        ]);

        // check that the email wasn't changed already
        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)
            ->find(TestFixtures::PROCESS_OWNER['id']);
        $this->assertSame(TestFixtures::PROCESS_OWNER['email'], $user->getEmail());

        // ... instead a queue message was dispatched
        $messenger = self::$container->get('messenger.default_bus');
        $messages = $messenger->getDispatchedMessages();
        $this->assertCount(1, $messages);
        $this->assertInstanceOf(UserEmailChangeMessage::class,
            $messages[0]['message']);
        $this->assertSame(TestFixtures::PROCESS_OWNER['id'],
            $messages[0]['message']->userId);
        $this->assertSame('new@zukunftsstadt.de',
            $messages[0]['message']->newEmail);
    }

    public function testEmailChangeFailsWithInvalidEmail(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROCESS_OWNER['id']]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROCESS_OWNER['email']]);

        $client->request('POST', $iri.'/change-email', ['json' => [
            'email'         => 'invalid',
            'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'email: This value is not a valid email address.',
        ]);
    }

    public function testEmailChangeFailsWithDuplicateEmail(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROCESS_OWNER['id']]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROCESS_OWNER['email']]);

        $client->request('POST', $iri.'/change-email', ['json' => [
            'email'         => TestFixtures::ADMIN['email'],
            'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'email: Email already exists.',
        ]);
    }

    public function testEmailChangeFailsWithoutEmail(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROCESS_OWNER['id']]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROCESS_OWNER['email']]);

        $client->request('POST', $iri.'/change-email', ['json' => [
            'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'email: validate.general.notBlank',
        ]);
    }

    public function testEmailChangeFailsWithoutValidationUrl(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROCESS_OWNER['id']]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROCESS_OWNER['email']]);

        $client->request('POST', $iri.'/change-email', ['json' => [
            'email' => 'new@zukunftsstadt.de',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validationUrl: validate.general.notBlank',
        ]);
    }

    public function testEmailChangeFailsUnauthenticated(): void
    {
        $client = self::createClient();
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROCESS_OWNER['email']]);

        $client->request('POST', $iri.'/change-email', ['json' => [
            'email'         => 'new@zukunftsstadt.de',
            'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testEmailChangeFailsWithoutPrivilege(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROJECT_MEMBER['id']]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROCESS_OWNER['email']]);

        $client->request('POST', $iri.'/change-email', ['json' => [
            'email'         => 'new@zukunftsstadt.de',
            'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
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

    // @todo
    // * user cannot set his own password to an empty value
    // * registerWithProject:
    // ** w/o motivation fails
    // ** w/o skills fails
    // ** skills too short
    // ** motivation too short
}
