<?php
declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Fund;
use App\Entity\FundApplication;
use App\Entity\FundConcretization;
use App\Entity\JuryCriterion;
use App\Entity\JuryRating;
use App\Entity\Process;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use App\Entity\UserObjectRole;
use App\Entity\Validation;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class TestFixtures extends Fixture implements FixtureGroupInterface
{
    public const ADMIN = [
        'id'        => 1, // because we persist him first
        'username'  => 'admin',
        'email'     => 'admin@zukunftsstadt.de',
        'roles'     => [User::ROLE_ADMIN],
        'password'  => 'no_secret',
        'createdAt' => '2018-01-01',
        'deletedAt' => null,
    ];

    public const PROCESS_OWNER = [
        'id'        => 2, // because we persist him second
        'username'  => 'po',
        'email'     => 'process@zukunftsstadt.de',
        'roles'     => [User::ROLE_PROCESS_OWNER],
        'password'  => 'my_precious',
        'createdAt' => '2018-02-01',
        'deletedAt' => null,
    ];

    public const DELETED_USER = [
        'id'        => 3, // because we persist him third
        'username'  => 'deleted_3',
        'email'     => 'deleted_3@zukunftsstadt.de',
        'roles'     => [],
        'password'  => 'empty',
        'createdAt' => '2018-02-01',
        'deletedAt' => '2019-12-01',
    ];

    public const JUROR = [
        'id'        => 4, // because we persist him forth
        'username'  => 'juror',
        'email'     => 'juror@zukunftsstadt.de',
        'roles'     => [],
        'password'  => 'cant_touch_this',
        'createdAt' => '2019-01-01',
        'deletedAt' => null,
    ];

    public const PROJECT_OWNER = [
        'id'        => 5, // because we persist him fifth
        'username'  => 'owner',
        'email'     => 'project@zukunftsstadt.de',
        'roles'     => [],
        'password'  => 'test123',
        'createdAt' => '2019-02-01',
        'deletedAt' => null,
    ];

    public const PROJECT_MEMBER = [
        'id'          => 6, // because we persist him sixth
        'username'    => 'member',
        'email'       => 'member@zukunftsstadt.de',
        'firstName'   => 'Peter',
        'lastName'    => 'Pan',
        'roles'       => [],
        'password'    => 'O_O',
        'createdAt'   => '2019-02-02',
        'isValidated' => true,
        'deletedAt'   => null,
    ];

    public const IDEA = [
        'id'               => 1, // because we persist it first
        'progress'         => Project::PROGRESS_IDEA,
        'shortDescription' => 'Car-free city center around the year',
    ];

    public const PROJECT = [
        'id'                    => 2, // because we persist it second
        'challenges'            => 'challenges',
        'delimitation'          => 'delimitation',
        'description'           => 'long description',
        'name'                  => 'Car-free Dresden',
        'profileSelfAssessment' => Project::SELF_ASSESSMENT_75_PERCENT,
        'progress'              => Project::PROGRESS_CREATING_PROFILE,
        'shortDescription'      => 'Car-free city center of Dresden',
        'goal'                  => 'goal',
        'vision'                => 'vision',
    ];

    public const LOCKED_PROJECT = [
        'id'                    => 3, // because we persist it third
        'name'                  => 'Locked Project',
        'isLocked'              => true,
        'progress'              => Project::PROGRESS_CREATING_PLAN,
        'shortDescription'      => 'this is locked',
    ];

    public const DELETED_PROJECT = [
        'id'                    => 4, // because we persist it forth
        'name'                  => 'Deleted Project',
        'progress'              => Project::PROGRESS_CREATING_PROFILE,
        'shortDescription'      => '',
        'deletedAt'             => '2019-12-12 12:12:12',
    ];

    public const ACTIVE_FUND = [
        'id'    => 1,
        'name'  => 'Future City',
        'state' => Fund::STATE_ACTIVE,
    ];

    public const INACTIVE_FUND = [
        'id'    => 2,
        'name'  => 'Culture City',
        'state' => Fund::STATE_INACTIVE,
    ];

    /**
     * @var UserPasswordEncoderInterface
     */
    private $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
    }

    public static function getGroups(): array
    {
        return ['test'];
    }

    public function load(ObjectManager $manager)
    {
        $loggerBackup = $manager->getConnection()->getConfiguration()
            ->getSQLLogger();
        $manager->getConnection()->getConfiguration()->setSQLLogger(null);

        $admin = $this->createUser(self::ADMIN);
        $manager->persist($admin);

        $processOwner = $this->createUser(self::PROCESS_OWNER);
        $manager->persist($processOwner);

        $deletedUser = $this->createUser(self::DELETED_USER);
        $manager->persist($deletedUser);

        $juror = $this->createUser(self::JUROR);
        $manager->persist($juror);

        $accountValidation = new Validation();
        $accountValidation->setUser($juror);
        $accountValidation->generateToken();
        $accountValidation->setType(Validation::TYPE_ACCOUNT);
        $accountValidation->setExpiresAt(new DateTimeImmutable("tomorrow"));
        $manager->persist($accountValidation);

        $projectOwner = $this->createUser(self::PROJECT_OWNER);
        $manager->persist($projectOwner);

        $emailValidation = new Validation();
        $emailValidation->setUser($projectOwner);
        $emailValidation->generateToken();
        $emailValidation->setType(Validation::TYPE_CHANGE_EMAIL);
        $emailValidation->setContent(['email' => 'new@zukunftsstadt.de']);
        $emailValidation->setExpiresAt(new DateTimeImmutable("tomorrow"));
        $manager->persist($emailValidation);

        $projectMember = $this->createUser(self::PROJECT_MEMBER);
        $manager->persist($projectMember);

        $pwValidation = new Validation();
        $pwValidation->setUser($projectMember);
        $pwValidation->generateToken();
        $pwValidation->setType(Validation::TYPE_RESET_PASSWORD);
        $pwValidation->setExpiresAt(new DateTimeImmutable("tomorrow"));
        $manager->persist($pwValidation);

        $process = new Process();
        $process->setName('Test-Process äüöß');
        $process->setDescription('Description for Test-Process');
        $process->setGoals(['first goal', 'second goal']);
        $process->setRegion('Dresden');
        $process->setImprint('FCP Test');
        $manager->persist($process);

        /**
         * Create an active fund
         */
        $activeFund = $this->createFund(self::ACTIVE_FUND, $process);
        $manager->persist($activeFund);
        /**
         * /Create an active fund
         */

        $inactiveFund = $this->createFund(self::INACTIVE_FUND, $process);
        $manager->persist($inactiveFund);

        $idea = $this->createProject(self::IDEA, $admin);
        $process->addProject($idea);
        $manager->persist($idea);

        /**
         * Create normal project with application & rating
         */
        $project = $this->createProject(self::PROJECT, $projectOwner,
            $idea, $projectOwner, $projectMember);
        $process->addProject($project);
        $manager->persist($project);

        $application = new FundApplication();
        $project->addApplication($application);
        $activeFund->addApplication($application);
        $manager->persist($application);

        $rating = new JuryRating();
        $rating->setJuror($juror);
        $rating->setRatings([1 => ['rating' => 3, 'comment' => 'more or less so-so']]);
        $application->addRating($rating);
        $manager->persist($rating);
        /**
         * /Create normal project with application & rating
         */

        /**
         * Create locked project
         */
        $lockedProject = $this->createProject(self::LOCKED_PROJECT,
            $projectOwner, $idea, $projectOwner, $projectMember);
        $process->addProject($lockedProject);
        $manager->persist($lockedProject);
        /**
         * /Create locked project
         */

        /**
         * Create deleted project
         */
        $deletedProject = $this->createProject(self::DELETED_PROJECT,
            $projectMember, $project);
        $process->addProject($deletedProject);
        $manager->persist($deletedProject);
        /**
         * /Create deleted project
         */

        // flush here, we need the IDs for the following entities
        $manager->flush();

        $processOwnerRole = new UserObjectRole();
        $processOwnerRole->setObjectType(Process::class);
        $processOwnerRole->setObjectId($process->getId());
        $processOwnerRole->setRole(UserObjectRole::ROLE_PROCESS_OWNER);
        $processOwner->addObjectRole($processOwnerRole);
        $manager->persist($processOwnerRole);

        $juryMemberRole = new UserObjectRole();
        $juryMemberRole->setObjectType(Fund::class);
        $juryMemberRole->setObjectId($activeFund->getId());
        $juryMemberRole->setRole(UserObjectRole::ROLE_JURY_MEMBER);
        $juror->addObjectRole($juryMemberRole);
        $manager->persist($juryMemberRole);

        $juryMemberRoleInactive = new UserObjectRole();
        $juryMemberRoleInactive->setObjectType(Fund::class);
        $juryMemberRoleInactive->setObjectId($inactiveFund->getId());
        $juryMemberRoleInactive->setRole(UserObjectRole::ROLE_JURY_MEMBER);
        $juror->addObjectRole($juryMemberRoleInactive);
        $manager->persist($juryMemberRoleInactive);

        $manager->flush();
        $manager->getConnection()->getConfiguration()->setSQLLogger($loggerBackup);
    }

    protected function createUser(array $data): User
    {
        $user = new User();
        $user->setUsername($data['username']);
        $user->setEmail($data['email']);
        $user->setRoles($data['roles']);

        if (isset($data['isValidated'])) {
            $user->setIsValidated($data['isValidated']);
        } else {
            $user->setIsValidated(true);
        }

        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }

        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }

        if (isset($data['createdAt'])) {
            $user->setCreatedAt(new \DateTimeImmutable($data['createdAt']));
        }

        if (isset($data['deletedAt'])) {
            $user->setDeletedAt(new \DateTimeImmutable($data['deletedAt']));
            $user->setPassword('');
        } else {
            $user->setPassword(
                $this->encoder->encodePassword(
                    $user,
                    $data['password'],
                )
            );
        }

        return $user;
    }

    protected function createProject(array $data, User $creator,
        ?Project $inspiration = null, ?User $owner = null, ?User $member = null)
    {
        $project = new Project();

        if (isset($data['challenges'])) {
            $project->setChallenges($data['challenges']);
        }
        if (isset($data['deletedAt'])) {
            $project->setDeletedAt(new DateTimeImmutable($data['deletedAt']));
        }
        if (isset($data['delimitation'])) {
            $project->setDelimitation($data['delimitation']);
        }
        if (isset($data['description'])) {
            $project->setDescription($data['description']);
        }
        if ($inspiration) {
            $project->setInspiration($inspiration);
        }
        if (isset($data['isLocked'])) {
            $project->setIsLocked($data['isLocked']);
        }
        if (isset($data['name'])) {
            $project->setName($data['name']);
        }
        if (isset($data['profileSelfAssessment'])) {
            $project->setProfileSelfAssessment($data['profileSelfAssessment']);
        }
        if (isset($data['progress'])) {
            $project->setProgress($data['progress']);
        }
        if (isset($data['shortDescription'])) {
            $project->setShortDescription($data['shortDescription']);
        }
        if (isset($data['state'])) {
            $project->setState($data['state']);
        }
        if (isset($data['goal'])) {
            $project->setGoal($data['goal']);
        }
        if (isset($data['vision'])) {
            $project->setVision($data['vision']);
        }

        $creator->addCreatedProject($project);

        if ($owner) {
            $ownership = new ProjectMembership();
            $ownership->setRole(ProjectMembership::ROLE_OWNER);
            $ownership->setSkills('owner skills');
            $ownership->setMotivation('owner motivation');
            $ownership->setTasks('owner tasks');
            $owner->addProjectMembership($ownership);
            $project->addMembership($ownership);
        }

        if ($member) {
            $membership = new ProjectMembership();
            $membership->setRole(ProjectMembership::ROLE_MEMBER);
            $membership->setSkills('member skills');
            $membership->setMotivation('member motivation');
            $membership->setTasks('member tasks');
            $member->addProjectMembership($membership);
            $project->addMembership($membership);
        }

        return $project;
    }

    protected function createFund($data, Process $process)
    {
        $fund = new Fund();
        $fund->setName($data['name']);
        $fund->setState($data['state']);

        $fund->setRegion('Dresden');
        $fund->setDescription('Funding from the BMBF');
        $fund->setSponsor('Bundesministerium für Forschung und Bildung');
        $fund->setImprint('Landeshauptstadt Dresden');
        $fund->setMinimumGrant(1000.);
        $fund->setMaximumGrant(5000.);
        $fund->setBudget(50000.);
        $fund->setCriteria(['must be sustainable']);
        $fund->setJurorsPerApplication(3);
        $fund->setSubmissionBegin(new DateTimeImmutable('2019-12-01'));
        $fund->setSubmissionEnd(new DateTimeImmutable('2019-12-31'));
        $fund->setRatingBegin(new DateTimeImmutable('2020-01-02'));
        $fund->setRatingEnd(new DateTimeImmutable('2020-01-16'));
        $fund->setBriefingDate(new DateTimeImmutable('2020-01-17'));
        $fund->setFinalJuryDate(new DateTimeImmutable('2020-02-01'));
        $process->addFund($fund);


        $concretization = new FundConcretization();
        $concretization->setQuestion('How does it help?');
        $concretization->setDescription('What does the project do for you?');
        $concretization->setMaxLength(280);
        $fund->addConcretization($concretization);

        $criterion = new JuryCriterion();
        $criterion->setName('Realistic expectations');
        $criterion->setQuestion('How realistic are the projects goals?');
        $fund->addJuryCriterion($criterion);

        return $fund;
    }
}
