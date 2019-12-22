<?php
declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\User;
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

    public function testGetUser(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::JUROR['email']]);
        $response = $client->request('GET', $iri);

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
            'objectRoles'        => [],
            'roles'              => [User::ROLE_USER],
            'projectMemberships' => [],
        ]);

        // $userData = $response->toArray();
        // @todo
        // * object roles prüfen
        // * memberships prüfen
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
            '@context' => '/contexts/Error',
            '@type' => 'hydra:Error',
            'hydra:title' => 'An error occurred',
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
            '@context' => '/contexts/Error',
            '@type' => 'hydra:Error',
            'hydra:title' => 'An error occurred',
            'hydra:description' => 'Not Found',
        ]);
    }

    public function testCreateUser(): void
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
            'code' => 401,
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
            'hydra:description' => 'email: This value should not be blank.',
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
            'hydra:description' => 'username: This value should not be blank.',
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
            'hydra:description' => 'username: validate.user.usernameExists',
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
            'hydra:description' => 'email: validate.user.emailExists',
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

    public function testUpdateUser(): void
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
            ['email' => TestFixtures::PROJECT_MEMBER['email']]);

        $client->request('PUT', $iri, ['json' => [
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
            'hydra:description' => 'email: validate.user.emailExists',
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

    public function testDeleteUser(): void
    {
        $before = new DateTimeImmutable();

        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_MEMBER['email']]);

        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        $user = static::$container->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy(['email' => TestFixtures::PROJECT_MEMBER['email']]);
        $this->assertNotNull($user);
        $this->assertTrue($user->isDeleted());
        $this->assertGreaterThan($before, $user->getDeletedAt());

        // @todo delete all non-essential data, test it
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
            'email' => TestFixtures::PROCESS_OWNER['email']
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

        // @todo 500 -> 400
        self::assertResponseStatusCodeSame(500);
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'User already deleted',
        ]);
    }

    // @todo normal users cannot filter by deletedat
}
