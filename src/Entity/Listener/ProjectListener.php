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
        if ($project->getProgress() === Project::PROGRESS_IDEA) {
            return;
        }

        if (!$project->isProfileComplete()) {
            $project->setProgress(Project::PROGRESS_CREATING_PROFILE);
            return;
        }

        $project->setProgress(Project::PROGRESS_CREATING_PLAN);
    }
}
