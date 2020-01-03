<?php
declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\Fund;
use App\Entity\JuryCriterion;
use App\PHPUnit\AuthenticatedClientTrait;
use App\PHPUnit\RefreshDatabaseTrait;

/**
 * @group JuryCriterionApi
 */
class JuryCriterionApiTest extends ApiTestCase
{
    use AuthenticatedClientTrait;
    use RefreshDatabaseTrait;

    /**
     * Test that no collection of criteria is available, not even for admins.
     */
    public function testCollectionNotAvailable(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ])->request('GET', '/jury_criteria');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "GET /jury_criteria": Method Not Allowed (Allow: POST)',
        ]);
    }

    public function testGetCriterionAsAdmin(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $iri = $this->findIriBy(JuryCriterion::class, ['id' => 1]);
        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(JuryCriterion::class);

        self::assertJsonContains([
            '@id'        => $iri,
            'name'     => 'Realistic expectations',
            'question' => 'How realistic are the projects goals?',
            'fund'     => [
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
        $response = $client->request('POST', '/jury_criteria', ['json' => [
            'name'     => 'New Criterion',
            'question' => 'How was your day?',
            'fund'     => $fundIri,
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(JuryCriterion::class);

        self::assertJsonContains([
            'name'     => 'New Criterion',
            'question' => 'How was your day?',
            'fund'     => [
                '@id'   => '/funds/1',
                '@type' => 'Fund',
            ],
        ]);

        $data = $response->toArray();
        $this->assertArrayNotHasKey('applications', $data['fund']);
        $this->assertArrayNotHasKey('process', $data['fund']);
        $this->assertArrayNotHasKey('concretizations', $data['fund']);
    }

    public function testCreateFailsUnauthenticated()
    {
        $client = static::createClient();

        $fundIri = $this->findIriBy(Fund::class, ['id' => 1]);
        $client->request('POST', '/jury_criteria', ['json' => [
            'name'     => 'New Criterion',
            'question' => 'How was your day?',
            'fund'     => $fundIri,
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
        $client->request('POST', '/jury_criteria', ['json' => [
            'name'     => 'New Criterion',
            'question' => 'How was your day?',
            'fund'     => $fundIri,
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

        $client->request('POST', '/jury_criteria', ['json' => [
            'name'     => 'New Criterion',
            'question' => 'How was your day?',
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

    public function testCreateWithoutNameFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $fundIri = $this->findIriBy(Fund::class, ['id' => 1]);
        $client->request('POST', '/jury_criteria', ['json' => [
            'question' => 'How was your day?',
            'fund'     => $fundIri,
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

    public function testCreateWithoutQuestionFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $fundIri = $this->findIriBy(Fund::class, ['id' => 1]);
        $client->request('POST', '/jury_criteria', ['json' => [
            'name'     => 'New Criterion',
            'fund'     => $fundIri,
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

    public function testCreateWithDuplicateNameFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $fundIri = $this->findIriBy(Fund::class, ['id' => 1]);
        $client->request('POST', '/jury_criteria', ['json' => [
            'name'     => 'Realistic expectations',
            'question' => 'How was your day?',
            'fund'     => $fundIri,
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

    public function testUpdate()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(JuryCriterion::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'name'     => 'New name',
            'question' => 'New question',
        ]]);

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(JuryCriterion::class);

        self::assertJsonContains([
            'name'     => 'New name',
            'question' => 'New question',
            'fund'     => [
                '@id'   => '/funds/1',
                '@type' => 'Fund',
            ],
        ]);
    }

    public function testUpdateFailsUnauthenticated()
    {
        $client = static::createClient();

        $iri = $this->findIriBy(JuryCriterion::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'name'     => 'New name',
            'question' => 'New question',
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

        $iri = $this->findIriBy(JuryCriterion::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'name'     => 'New name',
            'question' => 'New question',
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

        // add a second criterion to the db, we will try to name it like the first
        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        /* @var $fund Fund */
        $fund = $em->getRepository(Fund::class)->find(1);

        $criterion = new JuryCriterion();
        $criterion->setName('Simple name');
        $criterion->setQuestion('Question');
        $fund->addJuryCriterion($criterion);
        $em->persist($criterion);
        $em->flush();

        $iri = $this->findIriBy(JuryCriterion::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'name'     => 'Realistic expectations',
            'question' => 'New question',
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

    public function testUpdateWithEmptyNameFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(JuryCriterion::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'name'     => '',
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

    public function testUpdateWithEmptyQuestionFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_OWNER['email']
        ]);

        $iri = $this->findIriBy(JuryCriterion::class, ['id' => 1]);
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
        $iri = $this->findIriBy(JuryCriterion::class, ['id' => 1]);
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
        $iri = $this->findIriBy(JuryCriterion::class, ['id' => 1]);
        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        $deleted = static::$container->get('doctrine')
            ->getRepository(JuryCriterion::class)
            ->find(1);
        $this->assertNull($deleted);
    }

    public function testDeleteFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(JuryCriterion::class, ['id' => 1]);
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
        $iri = $this->findIriBy(JuryCriterion::class, ['id' => 1]);
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

        $iri = $this->findIriBy(JuryCriterion::class, ['id' => 1]);
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
        ])->request('DELETE', '/jury_criteria');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "DELETE /jury_criteria": Method Not Allowed (Allow: POST)',
        ]);
    }
}
