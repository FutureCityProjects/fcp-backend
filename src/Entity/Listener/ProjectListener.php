<?php
declare(strict_types=1);

namespace App\Entity\Listener;

use App\Entity\Helper\ProjectHelper;
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

        $helper = new ProjectHelper($project);

        if (!$helper->isProfileComplete()) {
            $project->setProgress(Project::PROGRESS_CREATING_PROFILE);
            return;
        }

        if (!$helper->isPlanComplete()) {
            $project->setProgress(Project::PROGRESS_CREATING_PLAN);
            return;
        }

        if (!$helper->isApplicationComplete()) {
            $project->setProgress(Project::PROGRESS_CREATING_APPLICATION);
            return;
        }

        // @todo this check could be the very first, but when should the
        // submitted-state reset? when a new fund is selected?
        if (!$helper->isApplicationSubmitted()) {
            $project->setProgress(Project::PROGRESS_SUBMITTING_APPLICATION);
            return;
        }

        $project->setProgress(Project::PROGRESS_APPLICATION_SUBMITTED);
    }
}
