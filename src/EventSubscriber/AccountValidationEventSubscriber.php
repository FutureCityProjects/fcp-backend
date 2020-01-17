<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Project;
use App\Entity\User;
use App\Entity\Validation;
use App\Event\ValidationConfirmedEvent;
use App\Event\ValidationExpiredEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

/**
 * Listen to the (account) validation events.
 */
class AccountValidationEventSubscriber
    implements EventSubscriberInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    public static function getSubscribedEvents()
    {
        return [
            ValidationConfirmedEvent::class => [
                ['onValidationConfirmed', 100],
                ['activateProjectsPostValidation', 50],
            ],
            ValidationExpiredEvent::class => [
                ['onValidationExpired', 100],
                ['deleteProjectsPostExpiration', 50],
            ],
        ];
    }

    /**
     * When an account validation was confirmed (via URL) set the validated flag.
     *
     * @param ValidationConfirmedEvent $event
     */
    public function onValidationConfirmed(ValidationConfirmedEvent $event)
    {
        if ($event->validation->getType() !== Validation::TYPE_ACCOUNT) {
            return;
        }

        if ($this->security()->isGranted(User::ROLE_USER)) {
            throw new AccessDeniedException('Forbidden for authenticated users.');
        }

        $user = $event->validation->getUser();
        if ($user->isDeleted()) {
            throw new NotFoundHttpException('Corresponding user not found.');
        }

        // set validated flag
        $user->setIsValidated(true);

        // no need to flush or remove the validation, this is done by the
        // event trigger.
    }

    /**
     * Called from the validation purge cron or when an expired validation
     * was requested via URL. Remove the corresponding user account if it
     * was not validated meanwhile.
     *
     * @param ValidationExpiredEvent $event
     */
    public function onValidationExpired(ValidationExpiredEvent $event)
    {
        if ($event->validation->getType() !== Validation::TYPE_ACCOUNT) {
            return;
        }

        $user = $event->validation->getUser();

        // remove the non-validated user to allow re-registration.
        // keep the user if he is marked validated, e.g. by an admin
        if (!$user->isValidated()) {
            // we need to keep the user in the DB, he may already has dependent
            // objects like created projects / ideas -> soft-delete only
            // @todo projects/ideas from are deleted too, completely remove user?
            $user->markDeleted();
        }

        // no need to flush or remove the validation, this is done by the
        // event trigger.
    }

    /**
     * After a user successfully validated his account activate his projects and
     * ideas. Projects/Ideas created via a new registration start deactivated to
     * only show validated entities in the marketplace etc.
     * There can't be any projects he deactivated himself as he would have
     * needed to be validated to login an do that.
     *
     * @param ValidationConfirmedEvent $event
     */
    public function activateProjectsPostValidation(ValidationConfirmedEvent $event)
    {
        if ($event->validation->getType() !== Validation::TYPE_ACCOUNT) {
            return;
        }

        $user = $event->validation->getUser();
        if (!$user->isValidated()) {
            return;
        }

        foreach ($user->getCreatedProjects() as $project) {
            if ($project->getState() === Project::STATE_DEACTIVATED) {
                $project->setState(Project::STATE_ACTIVE);
            }
        }
    }

    /**
     * When an account validation expired also delete the project/idea created
     * with his registration.
     * They were created with deactivated state so there should be no other
     * members.
     *
     * @param ValidationExpiredEvent $event
     */
    public function deleteProjectsPostExpiration(ValidationExpiredEvent $event)
    {
        if ($event->validation->getType() !== Validation::TYPE_ACCOUNT) {
            return;
        }

        $user = $event->validation->getUser();
        if (!$user->isDeleted()) {
            return;
        }

        foreach ($user->getCreatedProjects() as $project) {
            if ($project->getState() === Project::STATE_DEACTIVATED) {
                $this->entityManager()->remove($project);
            }
        }
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->container->get(__METHOD__);
    }

    private function security(): Security
    {
        return $this->container->get(__METHOD__);
    }
}
