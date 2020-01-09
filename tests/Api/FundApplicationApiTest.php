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

        $this->removeApplications(); // only one application per project&fund

        $fundIri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::ACTIVE_FUND['id']]);
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
                '@id'   => $fundIri,
                '@type' => 'Fund',
            ],
            'project' => [
                '@id'   => $projectIri,
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
        $this->removeApplications(); // only one application per project&fund

        $fundIri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::ACTIVE_FUND['id']]);
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
        $this->removeApplications(); // only one application per project&fund

        $fundIri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::ACTIVE_FUND['id']]);
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

        $this->removeApplications(); // only one application per project&fund

        $fundIri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::ACTIVE_FUND['id']]);
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

        $fundIri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::ACTIVE_FUND['id']]);
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
        $this->removeApplications(); // only one application per project&fund

        $fundIri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::ACTIVE_FUND['id']]);
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
        $this->removeApplications(); // only one application per project&fund

        $fundIri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::ACTIVE_FUND['id']]);
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

        $fundIri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::INACTIVE_FUND['id']]);
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
                '@type' => 'Fund',
                'id'    => TestFixtures::ACTIVE_FUND['id']
            ],
            'project'        => [
                '@type' => 'Project',
                'id'    => TestFixtures::PROJECT['id']
            ],
        ]);
    }

    public function testUpdateFailsUnauthenticated()
    {
        $client = static::createClient();

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

    public function testUpdateOfFundFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $fundIri = $this->findIriBy(Fund::class,
            ['id' => TestFixtures::INACTIVE_FUND['id']]);
        $iri = $this->findIriBy(FundApplication::class, ['id' => 1]);

        $client->request('PUT', $iri, ['json' => [
            'concretizations' => ['no more specifics'],
            'fund'            => $fundIri,
        ]]);

        self::assertResponseIsSuccessful();

        // concretizations got updated but fund didn't
        self::assertJsonContains([
            'concretizations' => [
                'no more specifics',
            ],
            'fund'        => [
                '@type' => 'Fund',
                'id'    => TestFixtures::ACTIVE_FUND['id'],
            ],
        ]);
    }

    public function testUpdateOfProjectFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::IDEA['id']]);
        $iri = $this->findIriBy(FundApplication::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'concretizations' => ['no more specifics'],
            'project'         => $projectIri,
        ]]);

        self::assertResponseIsSuccessful();

        // concretizations got updated but project didn't
        self::assertJsonContains([
            'concretizations' => [
                'no more specifics',
            ],
            'project'        => [
                'id'    => TestFixtures::PROJECT['id'],
                '@type' => 'Project',
            ],
        ]);
    }

    public function testUpdateWhenFundInactiveFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        /* @var $fund Fund */
        $fund = $em->getRepository(Fund::class)
            ->find(TestFixtures::ACTIVE_FUND['id']);
        $fund->setState(Fund::STATE_INACTIVE);
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

    public function testUpdateWhenProjectLockedFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_MEMBER['email']
        ]);

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
        $fund = $em->getRepository(Fund::class)
            ->find(TestFixtures::ACTIVE_FUND['id']);
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
    // * create for fund in a different process than the project fails
    // * update concretizations updates state
    // * submit
    // * submit unauth
    // * submit no priv
    // * submit w/ unfinished data / state
    // * submit w/ locked project
    // * submit w/ inactive fund
    // * submit w/ finished fund
}
