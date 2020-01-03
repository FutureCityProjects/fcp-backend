<?php
declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\Fund;
use App\Entity\FundApplication;
use App\Entity\FundConcretization;
use App\Entity\Project;
use App\PHPUnit\AuthenticatedClientTrait;
use App\PHPUnit\RefreshDatabaseTrait;

/**
 * @group FundApplicationApi
 */
class FundApplicationApiTest extends ApiTestCase
{
    use AuthenticatedClientTrait;
    use RefreshDatabaseTrait;

    protected function activateFund()
    {
        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        /* @var $fund Fund */
        $fund = $em->getRepository(Fund::class)->find(1);
        $fund->setState(Fund::STATE_ACTIVE);
        $em->flush();
    }

    protected function removeApplications()
    {
        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $apps = $em->getRepository(FundApplication::class)->findAll();
        foreach($apps as $app) {
            $em->remove($app);
        }

        $em->flush();
    }

    /**
     * Test that no collection of applications is available, not even for admins.
     */
    public function testCollectionNotAvailable(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ])->request('GET', '/fund_applications');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "GET /fund_applications": Method Not Allowed (Allow: POST)',
        ]);
    }

    public function testGetApplicationAsAdmin(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $iri = $this->findIriBy(FundApplication::class, ['id' => 1]);
        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(FundApplication::class);

        self::assertJsonContains([
            '@id'         => $iri,
            'fund'        => [
                '@id'   => '/funds/1',
                '@type' => 'Fund',
            ],
            'project'     => [
                '@id'   => '/projects/2',
                '@type' => 'Project',
            ],
        ]);
    }

    public function testCreate()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $this->activateFund(); // can only apply on active fund
        $this->removeApplications(); // only one application per project&fund

        $fundIri = $this->findIriBy(Fund::class, ['id' => 1]);
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $response = $client->request('POST', '/fund_applications', ['json' => [
            'project' => $projectIri,
            'fund'    => $fundIri,
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(FundConcretization::class);

        self::assertJsonContains([
            'fund' => [
                '@id'   => '/funds/1',
                '@type' => 'Fund',
            ],
            'project' => [
                '@id'   => '/projects/2',
                '@type' => 'Project',
            ],
            'state'                        => FundApplication::STATE_OPEN,
            'concretizations'              => null,
            'concretizationSelfAssessment' => FundApplication::SELF_ASSESSMENT_0_PERCENT,
        ]);

        $data = $response->toArray();
        $this->assertArrayNotHasKey('ratings', $data);
        $this->assertArrayNotHasKey('juryComment', $data);
        $this->assertArrayNotHasKey('juryOrder', $data);

        $this->assertArrayNotHasKey('applications', $data['fund']);
        $this->assertArrayNotHasKey('process', $data['fund']);
        $this->assertArrayNotHasKey('juryCriteria', $data['fund']);
    }

    public function testCreateFailsUnauthenticated()
    {
        $client = static::createClient();
        $this->activateFund(); // can only apply on active fund
        $this->removeApplications(); // only one application per project&fund

        $fundIri = $this->findIriBy(Fund::class, ['id' => 1]);
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $client->request('POST', '/fund_applications', ['json' => [
            'project' => $projectIri,
            'fund'    => $fundIri,
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
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);
        $this->activateFund(); // can only apply on active fund
        $this->removeApplications(); // only one application per project&fund

        $fundIri = $this->findIriBy(Fund::class, ['id' => 1]);
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $client->request('POST', '/fund_applications', ['json' => [
            'project' => $projectIri,
            'fund'    => $fundIri,
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
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $this->activateFund(); // can only apply on active fund
        $this->removeApplications(); // only one application per project&fund

        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $client->request('POST', '/fund_applications', ['json' => [
            'project' => $projectIri,
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

    public function testCreateWithoutProjectFails()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $this->activateFund(); // can only apply on active fund
        $this->removeApplications(); // only one application per project&fund

        $fundIri = $this->findIriBy(Fund::class, ['id' => 1]);
        $client->request('POST', '/fund_applications', ['json' => [
            'fund' => $fundIri,
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

    public function testCreateDuplicateFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);

        $this->activateFund(); // can only apply on active fund

        $fundIri = $this->findIriBy(Fund::class, ['id' => 1]);
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $client->request('POST', '/fund_applications', ['json' => [
            'project' => $projectIri,
            'fund'    => $fundIri,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'fund: Duplicate fund application found.',
        ]);
    }

    public function testCreateFailsForLockedProject()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);
        $this->activateFund(); // can only apply on active fund
        $this->removeApplications(); // only one application per project&fund

        $fundIri = $this->findIriBy(Fund::class, ['id' => 1]);
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::LOCKED_PROJECT['id']]);
        $client->request('POST', '/fund_applications', ['json' => [
            'project' => $projectIri,
            'fund'    => $fundIri,
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

    public function testCreateFailsForIdea()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);
        $this->activateFund(); // can only apply on active fund
        $this->removeApplications(); // only one application per project&fund

        $fundIri = $this->findIriBy(Fund::class, ['id' => 1]);
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::IDEA['id']]);
        $client->request('POST', '/fund_applications', ['json' => [
            'project' => $projectIri,
            'fund'    => $fundIri,
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

    public function testCreateFailsForInactiveFund()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);
        $this->removeApplications(); // only one application per project&fund

        $fundIri = $this->findIriBy(Fund::class, ['id' => 1]);
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $client->request('POST', '/fund_applications', ['json' => [
            'project' => $projectIri,
            'fund'    => $fundIri,
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

    public function testUpdate()
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);
        $this->activateFund(); // can only update when fund is active

        $iri = $this->findIriBy(FundApplication::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'concretizations' => ['more specifics'],
        ]]);

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(FundConcretization::class);

        self::assertJsonContains([
            'concretizations' => [
                'more specifics',
            ],
            'concretizationSelfAssessment' => FundApplication::SELF_ASSESSMENT_0_PERCENT,
            'fund'        => [
                '@id'   => '/funds/1',
                '@type' => 'Fund',
            ],
            'project'        => [
                '@id'   => '/projects/2',
                '@type' => 'Project',
            ],
        ]);
    }

    public function testUpdateFailsUnauthenticated()
    {
        $client = static::createClient();
        $this->activateFund(); // can only update when fund is active

        $iri = $this->findIriBy(FundApplication::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'concretizations' => ['more specifics'],
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
        $this->activateFund(); // can only update when fund is active

        $iri = $this->findIriBy(FundApplication::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'concretizations' => ['more specifics'],
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

    public function testUpdateFundFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);
        $this->activateFund(); // can only update when fund is active

        $fundIri = $this->findIriBy(Fund::class, ['id' => 1]);
        $iri = $this->findIriBy(FundApplication::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'concretizations' => ['more specifics'],
            'fund'            => $fundIri,
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

    public function testUpdateProjectFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);
        $this->activateFund(); // can only update when fund is active

        $projectIri = $this->findIriBy(Project::class, ['id' => 1]);
        $iri = $this->findIriBy(FundApplication::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'concretizations' => ['more specifics'],
            'project'         => $projectIri,
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

    public function testUpdateWhenFundInactiveFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $iri = $this->findIriBy(FundApplication::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'concretizations' => ['more specifics'],
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

    public function testUpdateWhenProjectLockedFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);
        $this->activateFund(); // can only update when fund is active

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();

        /** @var Project $project */
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $project->setIsLocked(true);
        $em->flush();

        $iri = $this->findIriBy(FundApplication::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'concretizations' => ['more specifics'],
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

    public function testUpdateSubmittedApplicationFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);
        $this->activateFund(); // can only update when fund is active

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();

        /** @var FundApplication $app */
        $app = $em->getRepository(FundApplication::class)->find(1);
        $app->setState(FundApplication::STATE_SUBMITTED);
        $em->flush();

        $iri = $this->findIriBy(FundApplication::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'concretizations' => ['more specifics'],
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

    public function testDelete(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OWNER['email']
        ]);
        $this->activateFund();

        $iri = $this->findIriBy(FundApplication::class, ['id' => 1]);
        $client->request('DELETE', $iri);
        static::assertResponseStatusCodeSame(204);

        $deleted = static::$container->get('doctrine')
            ->getRepository(FundApplication::class)
            ->find(1);
        $this->assertNull($deleted);
    }

    public function testDeleteFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $this->activateFund();

        $iri = $this->findIriBy(FundApplication::class, ['id' => 1]);
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
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);
        $this->activateFund();

        $iri = $this->findIriBy(FundApplication::class, ['id' => 1]);
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

    public function testDeleteFailsWhenFundIsFinished(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::JUROR['email']
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        /* @var $fund Fund */
        $fund = $em->getRepository(Fund::class)->find(1);
        $fund->setState(Fund::STATE_FINISHED);
        $em->flush();

        $iri = $this->findIriBy(FundApplication::class, ['id' => 1]);
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

    public function testDeleteFailsWhenProjectIsLocked(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::JUROR['email']
        ]);
        $this->activateFund();

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        /** @var Project $project */
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $project->setIsLocked(true);
        $em->flush();

        $iri = $this->findIriBy(FundApplication::class, ['id' => 1]);
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

    public function testDeleteSubmittedApplicationFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::JUROR['email']
        ]);
        $this->activateFund();

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();

        /** @var FundApplication $app */
        $app = $em->getRepository(FundApplication::class)->find(1);
        $app->setState(FundApplication::STATE_SUBMITTED);
        $em->flush();

        $iri = $this->findIriBy(FundApplication::class, ['id' => 1]);
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
        ])->request('DELETE', '/fund_applications');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "DELETE /fund_applications": Method Not Allowed (Allow: POST)',
        ]);
    }

    // @todo
    // * update concretizations updates state
    // * submit
    // * submit unauth
    // * submit no priv
    // * submit w/ unfinished data / state
    // * submit w/ locked project
    // * submit w/ inactive fund
    // * submit w/ finished fund
}
