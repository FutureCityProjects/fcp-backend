<?php
declare(strict_types=1);

namespace App\Entity\Listener;

use App\Entity\Project;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class ProjectListener
{
    /**
     * Automatically update the progress of the project when the members fill
     * in data.
     *
     * @param Project $project
     * @param LifecycleEventArgs $args
     */
    public function preUpdate(Project $project, LifecycleEventArgs $args)
    {
        $project->recalculateProgress();
    }
}
