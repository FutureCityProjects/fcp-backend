<?php
declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\User;
use App\PHPUnit\AuthenticatedClientTrait;
use App\PHPUnit\RefreshDatabaseTrait;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;

/**
 * @group AuthApi
 */
class AuthApiTest extends ApiTestCase
{
    use AuthenticatedClientTrait;
    use RefreshDatabaseTrait;

    public function testAuthRequiresPassword(): void
    {
        static::createClient()->request('POST', '/authentication_token', [
            'json' => [
                'username' => null,
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type', 'application/json');

        self::assertJsonContains([
            'type'   => 'https://tools.ietf.org/html/rfc2616#section-10',
            'title'  => 'An error occurred',
            'detail' => 'The key "password" must be provided.',
        ]);
    }

    public function testAuthRequiresUsername(): void
    {
        static::createClient()->request('POST', '/authentication_token', [
            'json' => [
                'password' => null,
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type', 'application/json');

        self::assertJsonContains([
            'type'   => 'https://tools.ietf.org/html/rfc2616#section-10',
            'title'  => 'An error occurred',
            'detail' => 'The key "username" must be provided.',
        ]);
    }

    public function testAuthRequiresUsernameToBeSet(): void
    {
        static::createClient()->request('POST', '/authentication_token', [
            'json' => [
                'password' => "null",
                'username' => null,
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type', 'application/json');

        self::assertJsonContains([
            'type'   => 'https://tools.ietf.org/html/rfc2616#section-10',
            'title'  => 'An error occurred',
            'detail' => 'The key "username" must be a string.',
        ]);
    }

    public function testAuthRequiresPasswordToBeSet(): void
    {
        static::createClient()->request('POST', '/authentication_token', [
            'json' => [
                'password' => null,
                'username' => "null",
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type', 'application/json');

        self::assertJsonContains([
            'type'   => 'https://tools.ietf.org/html/rfc2616#section-10',
            'title'  => 'An error occurred',
            'detail' => 'The key "password" must be a string.',
        ]);
    }

    public function testAuthWorks(): void
    {
        $r = static::createClient()->request('POST', '/authentication_token', ['json' => [
            'username' => TestFixtures::ADMIN['username'],
            'password' => TestFixtures::ADMIN['password'],
        ]]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $auth = $r->toArray();
        $this->assertArrayHasKey('token', $auth);

        /** @var $decoder JWTEncoderInterface */
        $decoder = static::$container->get(JWTEncoderInterface::class);
        $decoded = $decoder->decode($auth['token']);

        $this->assertArrayHasKey('exp', $decoded);
        $this->assertSame(TestFixtures::ADMIN['username'], $decoded['username']);
        $this->assertSame([User::ROLE_ADMIN, User::ROLE_USER], $decoded['roles']);
        $this->assertSame(TestFixtures::ADMIN['id'], $decoded['id']);
    }

    public function testAuthFailsWithUnknownUsername(): void
    {
        static::createClient()->request('POST', '/authentication_token', ['json' => [
            'username' => 'not-found',
            'password' => 'irrelevant',
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type', 'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'user.invalidCredentials',
        ]);
    }

    public function testAuthFailsWithWrongPassword(): void
    {
        $r = static::createClient()->request('POST', '/authentication_token', ['json' => [
            'username' => TestFixtures::ADMIN['username'],
            'password' => 'this-is-wrong',
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type', 'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'user.invalidCredentials',
        ]);
    }

    public function testAuthFailsWithInactiveUser(): void
    {
        $client = static::createClient();

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $admin = $em->getRepository(User::class)->find(TestFixtures::ADMIN['id']);
        $admin->setIsActive(false);
        $em->flush();

        $client->request('POST', '/authentication_token', ['json' => [
            'username' => TestFixtures::ADMIN['username'],
            'password' => TestFixtures::ADMIN['password'],
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type', 'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'user.notActivated',
        ]);
    }

    public function testAuthFailsWithNotValidatedUser(): void
    {
        $client = static::createClient();

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $admin = $em->getRepository(User::class)->find(TestFixtures::ADMIN['id']);
        $admin->setIsValidated(false);
        $em->flush();

        $client->request('POST', '/authentication_token', ['json' => [
            'username' => TestFixtures::ADMIN['username'],
            'password' => TestFixtures::ADMIN['password'],
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type', 'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'user.notValidated',
        ]);
    }

    public function testRefreshTokenWorks(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);
        $response = $client->request('GET', '/refresh_token');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $auth = $response->toArray();

        $this->assertArrayHasKey('token', $auth);

        /** @var $decoder JWTEncoderInterface */
        $decoder = static::$container->get(JWTEncoderInterface::class);
        $decoded = $decoder->decode($auth['token']);

        $this->assertArrayHasKey('exp', $decoded);
        $this->assertSame(TestFixtures::ADMIN['username'], $decoded['username']);
        $this->assertSame([User::ROLE_ADMIN, User::ROLE_USER], $decoded['roles']);
    }

    /**
     * requires zalas/phpunit-globals:
     * @env JWT_TOKEN_TTL=3
     */
    public function testRefreshFailsWithExpiredToken(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);
        sleep(5);
        $client->request('GET', '/refresh_token');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'Expired JWT Token',
        ]);
    }

    public function testRefreshTokenFailsUnauthenticated(): void
    {
        $client = static::createClient();

        $client->request('GET', '/refresh_token');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type', 'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }
}
