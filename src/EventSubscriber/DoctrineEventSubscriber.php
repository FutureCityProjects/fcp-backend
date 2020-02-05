<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\FundApplication;
use App\Entity\Project;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

class DoctrineEventSubscriber implements EventSubscriber
{
    public function getSubscribedEvents()
    {
        return [
            Events::onFlush,
        ];
    }

    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        $entities = array_merge(
            $uow->getScheduledEntityInsertions(),
            $uow->getScheduledEntityUpdates()
        );

        foreach ($entities as $entity) {
            if (!($entity instanceof FundApplication)) {
                continue;
            }

            // mark the project as updated if an application for it is created
            // or updated, the project should not be marked as inactive when
            // only its application(s) changed
            $entity->getProject()->setUpdatedAt(new \DateTimeImmutable());

            // Trigger state/progress update in the correct order: project
            // progress depends on application state.
            // We need to do this here as onFlush is called before preUpdate and
            // in the entityListeners preUpdate no changes to other entities can
            // be triggered, not even by using the UnitOfWork

            // because this is done here we don't need a preUpdate listener
            // for FundApplication
            $entity->recalculateState();

            $entity->getProject()->recalculateProgress();

            // only changing the entity is not enough, we need to force the
            // UnitOfWork to include the changeset
            $md = $em->getClassMetadata(Project::class);
            $uow->recomputeSingleEntityChangeSet($md, $entity->getProject());
        }
    }
}
