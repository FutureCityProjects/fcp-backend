<?php
declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\Fund;
use App\Entity\FundConcretization;
use App\PHPUnit\AuthenticatedClientTrait;
use App\PHPUnit\RefreshDatabaseTrait;

class FundConcretizationApiTest extends ApiTestCase
{
    use AuthenticatedClientTrait;
    use RefreshDatabaseTrait;

    /**
     * Test that no collection of concretizations is available, not even for admins.
     */
    public function testCollectionNotAvailable(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ])->request('GET', '/fund_concretizations');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "GET /fund_concretizations": Method Not Allowed (Allow: POST)',
        ]);
    }

    public function testGetConcretizationAsAdmin(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $iri = $this->findIriBy(FundConcretization::class, ['id' => 1]);
        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(FundConcretization::class);

        self::assertJsonContains([
            '@id'         => $iri,
            'question'    => 'How does it help?',
            'description' => 'What does the project do for you?',
            'maxLength'   => 280,
            'fund'        => [
                '@id'   => '/funds/1',
                '@type' => 'Fund',
            ],
        ]);
    }

    public function testCreate()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $fundIri = $this->findIriBy(Fund::class, ['id' => 1]);
        $response = $client->request('POST', '/fund_concretizations', ['json' => [
            'question'    => 'What does it do?',
            'description' => 'Explain please',
            'maxLength'   => 280,
            'fund'        => $fundIri,
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(FundConcretization::class);

        self::assertJsonContains([
            'question'    => 'What does it do?',
            'description' => 'Explain please',
            'maxLength'   => 280,
            'fund'        => [
                '@id'   => '/funds/1',
                '@type' => 'Fund',
            ],
        ]);

        $data = $response->toArray();
        $this->assertArrayNotHasKey('applications', $data['fund']);
        $this->assertArrayNotHasKey('process', $data['fund']);
        $this->assertArrayNotHasKey('juryCriteria', $data['fund']);
    }

    public function testCreateFailsUnauthenticated()
    {
        $client = static::createClient();

        $fundIri = $this->findIriBy(Fund::class, ['id' => 1]);
        $client->request('POST', '/fund_concretizations', ['json' => [
            'question'    => 'What does it do?',
            'description' => 'Explain please',
            'maxLength'   => 280,
            'fund'        => $fundIri,
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testCreateFailsWithoutPrivilege()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::JUROR['email']
        ]);

        $fundIri = $this->findIriBy(Fund::class, ['id' => 1]);
        $client->request('POST', '/fund_concretizations', ['json' => [
            'question'    => 'What does it do?',
            'description' => 'Explain please',
            'maxLength'   => 280,
            'fund'        => $fundIri,
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

    public function testCreateWithoutFundFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $client->request('POST', '/fund_concretizations', ['json' => [
            'question'    => 'What does it do?',
            'maxLength'   => 280,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'fund: This value should not be null.',
        ]);
    }

    public function testCreateWithEmptyMaxLengthFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $fundIri = $this->findIriBy(Fund::class, ['id' => 1]);
        $client->request('POST', '/fund_concretizations', ['json' => [
            'question'    => 'What does it do?',
            'fund'        => $fundIri,
            'maxLength'   => null,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'The type of the "maxLength" attribute must be "int", "NULL" given.',
        ]);
    }

    public function testCreateWithoutQuestionFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $fundIri = $this->findIriBy(Fund::class, ['id' => 1]);
        $client->request('POST', '/fund_concretizations', ['json' => [
            'maxLength'   => 280,
            'fund'        => $fundIri,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'question: This value should not be blank.',
        ]);
    }

    public function testCreateWithDuplicateQuestionFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $fundIri = $this->findIriBy(Fund::class, ['id' => 1]);
        $client->request('POST', '/fund_concretizations', ['json' => [
            'question'    => 'How does it help?',
            'fund'        => $fundIri,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'question: Question already exists.',
        ]);
    }

    public function testUpdate()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(FundConcretization::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'question'    => 'New question',
            'description' => 'New description',
            'maxLength'   => 55,
        ]]);

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(FundConcretization::class);

        self::assertJsonContains([
            'question'    => 'New question',
            'description' => 'New description',
            'maxLength'   => 55,
            'fund'        => [
                '@id'   => '/funds/1',
                '@type' => 'Fund',
            ],
        ]);
    }

    public function testUpdateFailsUnauthenticated()
    {
        $client = static::createClient();

        $iri = $this->findIriBy(FundConcretization::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'question'    => 'New question',
            'description' => 'New description',
            'maxLength'   => 55,
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testUpdateFailsWithoutPrivilege()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::JUROR['email']
        ]);

        $iri = $this->findIriBy(FundConcretization::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'question'    => 'New question',
            'description' => 'New description',
            'maxLength'   => 55,
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

    public function testUpdateWithDuplicateQuestionFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        // add a second concretization to the db, we will try to name it like the first
        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        /* @var $fund Fund */
        $fund = $em->getRepository(Fund::class)->find(1);

        $concretization = new FundConcretization();
        $concretization->setQuestion('Question');
        $fund->addConcretization($concretization);
        $em->persist($concretization);
        $em->flush();

        $iri = $this->findIriBy(FundConcretization::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'question'    => 'How does it help?',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'question: Question already exists.',
        ]);
    }

    public function testUpdateWithEmptyMaxLengthFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(FundConcretization::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'question'  => 'Test',
            'maxLength' => null,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'The type of the "maxLength" attribute must be "int", "NULL" given.',
        ]);
    }

    public function testUpdateWithInvalidMaxLengthFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(FundConcretization::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'question'  => 'Test',
            'maxLength' => -1,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'maxLength: maxLength is out of range.',
        ]);
    }

    public function testUpdateWithoutQuestionFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(FundConcretization::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'question' => '',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'question: This value should not be blank.',
        ]);
    }

    public function testUpdateFundFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $fundIri = $this->findIriBy(Fund::class, ['id' => 1]);
        $iri = $this->findIriBy(FundConcretization::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'question' => 'new question',
            'fund'     => $fundIri,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Extra attributes are not allowed ("fund" are unknown).',
        ]);
    }

    public function testDelete(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);
        $iri = $this->findIriBy(FundConcretization::class, ['id' => 1]);
        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        $deleted = static::$container->get('doctrine')
            ->getRepository(FundConcretization::class)
            ->find(1);
        $this->assertNull($deleted);
    }

    public function testDeleteFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(FundConcretization::class, ['id' => 1]);
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
        $iri = $this->findIriBy(FundConcretization::class, ['id' => 1]);
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
            'email' => TestFixtures::JUROR['email']
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        /* @var $fund Fund */
        $fund = $em->getRepository(Fund::class)->find(1);
        $fund->setState(Fund::STATE_ACTIVE);
        $em->flush();

        $iri = $this->findIriBy(FundConcretization::class, ['id' => 1]);
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
        ])->request('DELETE', '/fund_concretizations');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "DELETE /fund_concretizations": Method Not Allowed (Allow: POST)',
        ]);
    }
}
