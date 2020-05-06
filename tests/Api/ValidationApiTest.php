<?php
declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\Process;
use App\Entity\Project;
use App\Entity\User;
use App\Entity\Validation;
use App\Message\UserValidatedMessage;
use App\PHPUnit\AuthenticatedClientTrait;
use App\PHPUnit\RefreshDatabaseTrait;
use DateTimeImmutable;

/**
 * @group ValidationApi
 */
class ValidationApiTest extends ApiTestCase
{
    use AuthenticatedClientTrait;
    use RefreshDatabaseTrait;

    public function testGetCollectionNotAvailable(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ])->request('GET', '/validations');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "GET /validations"',
        ]);
    }

    public function testGetFailsUnauthenticated(): void
    {
        $client = static::createClient();

        $iri = $this->findIriBy(Validation::class, ['id' => 1]);
        $client->request('GET', $iri);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testGetFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(Validation::class, ['id' => 1]);
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

    public function testCreateNotAvailable(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ])->request('POST', '/validations', ['json' => [
            'user'  => '/users/1',
            'type'  => Validation::TYPE_ACCOUNT,
            'token' => 'irrelevant',
        ]]);

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "POST /validations"',
        ]);
    }

    public function testUpdateNotAvailable(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $iri = $this->findIriBy(Validation::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'token' => '123fail',
        ]]);

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "PUT /validations/1": Method Not Allowed (Allow: GET)',
        ]);
    }

    public function testConfirmEmailChange(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();

        // ID 2 is the owners email change validation
        $token = $em->getRepository(Validation::class)
            ->find(2)
            ->getToken();

        $before = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_OWNER['id']);
        $this->assertSame(TestFixtures::PROJECT_OWNER['email'], $before->getEmail());
        $em->clear();

        $client->request('POST', '/validations/2/confirm', ['json' => [
            'token' => $token,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'success' => true,
            'message' => 'Validation successful',
        ]);

        $after = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_OWNER['id']);
        $this->assertSame('new@zukunftsstadt.de', $after->getEmail());
        $this->assertCount(0, $after->getValidations());
    }

    public function testConfirmAccountValidation(): void
    {
        $client = static::createClient();
        $em = static::$kernel->getContainer()->get('doctrine')->getManager();

        /** @var User $before */
        $before = $em->getRepository(User::class)
            ->find(TestFixtures::JUROR['id']);
        $before->setIsValidated(false);

        $token = $before->getValidations()[0]->getToken();
        $process = $em->getRepository(Process::class)->find(1);

        $project = new Project();
        $project->setShortDescription("this is deactivated");
        $project->setState(Project::STATE_DEACTIVATED);
        $project->setProgress(Project::PROGRESS_IDEA);
        $project->setProcess($process);
        $project->setCreatedBy($before);
        $em->persist($project);

        $em->flush();
        $em->clear();

        $client->request('POST', '/validations/1/confirm', ['json' => [
            'token'    => $token,
            'password' => 'new-password',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'success' => true,
            'message' => 'Validation successful',
        ]);

        /** @var User $after */
        $after = $em->getRepository(User::class)
            ->find(TestFixtures::JUROR['id']);
        $this->assertTrue($after->isValidated());
        $this->assertCount(0, $after->getValidations());

        // project was activated on validation confirm
        $this->assertCount(1, $after->getCreatedProjects());
        $this->assertSame(Project::STATE_ACTIVE,
            $after->getCreatedProjects()[0]->getState());

        // a message was pushed to the bus, to notify process owners
        $messenger = self::$container->get('messenger.default_bus');
        $messages = $messenger->getDispatchedMessages();
        $this->assertCount(1, $messages);
        $this->assertInstanceOf(UserValidatedMessage::class,
            $messages[0]['message']);
    }

    public function testConfirmPasswordReset(): void
    {
        $client = static::createClient();
        $em = static::$kernel->getContainer()->get('doctrine')->getManager();

        // ID 3 is the members PW reset validation
        $token = $em->getRepository(Validation::class)
            ->find(3)
            ->getToken();

        $oldPW = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_MEMBER['id'])
            ->getPassword();
        $em->clear();

        $client->request('POST', '/validations/3/confirm', ['json' => [
            'token'    => $token,
            'password' => 'new-password',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'success' => true,
            'message' => 'Validation successful',
        ]);

        $after  = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_MEMBER['id']);
        $this->assertNotSame($oldPW, $after->getPassword());
        $this->assertCount(0, $after->getValidations());
    }


    public function testConfirmAccountValidationFailsAuthenticated(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();

        // ID 1 is the jurors account validation
        $token = $em->getRepository(Validation::class)
            ->find(1)
            ->getToken();

        $client->request('POST', '/validations/1/confirm', ['json' => [
            'token' => $token,
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

    public function testConfirmEmailChangeFailsAsOtherUser(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();

        // ID 2 is the owners email change validation
        $token = $em->getRepository(Validation::class)
            ->find(2)
            ->getToken();

        $client->request('POST', '/validations/2/confirm', ['json' => [
            'token' => $token,
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

    public function testConfirmPasswordResetFailsAuthenticated(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();

        // ID 3 is the members PW reset validation
        $token = $em->getRepository(Validation::class)
            ->find(3)
            ->getToken();

        $client->request('POST', '/validations/3/confirm', ['json' => [
            'token' => $token,
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

    public function testConfirmWithWrongTokenFails(): void
    {
        static::createClient()->request('POST', '/validations/1/confirm', ['json' => [
            'token' => 'fails',
        ]]);

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

    public function testConfirmFailsWhenExpired(): void
    {
        $client = static::createClient();

        // ID 1 is the owners email change validation
        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        /** @var Validation $validation */
        $validation = $em->getRepository(Validation::class)->find(1);
        $validation->setExpiresAt(new DateTimeImmutable("yesterday"));
        $em->flush();;
        $em->clear();

        $client->request('POST', '/validations/1/confirm', ['json' => [
            'token' => $validation->getToken(),
        ]]);

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

    public function testDeleteNotAvailable(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);
        $iri = $this->findIriBy(Validation::class, ['id' => 1]);
        $client->request('DELETE', $iri);

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "DELETE /validations/1": Method Not Allowed (Allow: GET)',
        ]);
    }

    /**
     * Test that the DELETE operation for the whole collection is not available.
     */
    public function testCollectionDeleteNotAvailable(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ])->request('DELETE', '/validations');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "DELETE /validations"',
        ]);
    }

    // @todo
    // * fail email validation for deleted user
    // * fail pw reset for deleted user
    // * fail pw reset for inactive user
    // * fail account validation for deleted user
}
