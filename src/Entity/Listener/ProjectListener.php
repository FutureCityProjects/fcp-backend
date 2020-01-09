<?php
declare(strict_types=1);

namespace App\Entity\Listener;

use App\Entity\Project;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class ProjectListener
{
    public function preUpdate(Project $project, LifecycleEventArgs $args)
    {
        if (!$project->isProfileComplete()) {
            $project->setProgress(Project::PROGRESS_CREATING_PROFILE);
            return;
        }

        $project->setProgress(Project::PROGRESS_CREATING_PLAN);
    }
}
