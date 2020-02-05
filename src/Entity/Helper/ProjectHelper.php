<?php
declare(strict_types=1);

namespace App\Entity\Helper;

use App\Entity\FundApplication;
use App\Entity\Project;

class ProjectHelper
{
    protected Project $project;

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    /**
     * Checks if all required profile fields are set and the self assessment
     * is 100%, if yes return true, else false.
     *
     * @return bool
     */
    public function isPlanAvailable() : bool
    {
        if ($this->project->getState() === Project::STATE_DEACTIVATED) {
            return false;
        }

        if ($this->project->getProfileSelfAssessment()
            !== Project::SELF_ASSESSMENT_100_PERCENT
        ) {
            return false;
        }

        if (!$this->project->getName()
            || !$this->project->getShortDescription()
            || !$this->project->getChallenges()
            || !$this->project->getGoal()
            || !$this->project->getVision()
            || !$this->project->getDescription()
            || !$this->project->getDelimitation()
        ) {
            return false;
        }

        return true;
    }

    public function isApplicationAvailable(): bool
    {
        if ($this->project->getState() === Project::STATE_DEACTIVATED) {
            return false;
        }

        if ($this->project->getPlanSelfAssessment()
            !== Project::SELF_ASSESSMENT_100_PERCENT
        ) {
            return false;
        }

        // @todo support multiple applications
        if ($this->project->getApplications()->count() === 0) {
            return false;
        }
        $application = $this->project->getApplications()[0];

        // concretizations have not been filled in
        if ($application->getState() !== FundApplication::STATE_DETAILING) {
            return false;
        }

        return true;
    }

    public function isSubmissionAvailable(): bool
    {
        if ($this->project->getPlanSelfAssessment()
            !== Project::SELF_ASSESSMENT_100_PERCENT
        ){
            return false;
        }

        if ($this->project->getApplications()->count() === 0) {
            return false;
        }

        // @todo support multiple applications
        $application = $this->project->getApplications()[0];

        if ($application->getState() !== FundApplication::STATE_DETAILING) {
            return false;
        }

        if ($application->getApplicationSelfAssessment()
            !== FundApplication::SELF_ASSESSMENT_100_PERCENT
        ) {
            return false;
        }

        return true;
    }

    public function isApplicationSubmitted(): bool
    {
        if ($this->project->getApplications()->count() === 0) {
            return false;
        }

        // @todo support multiple applications
        $application = $this->project->getApplications()[0];

        return $application->getState() === FundApplication::STATE_SUBMITTED;
    }

    public function hasTasks() : bool
    {
        $tasks = $this->project->getTasks();
        return $tasks !== null && count($tasks) > 0;
    }

    public function getTaskIDs(): array
    {
        $tasks = $this->project->getTasks();
        if ($tasks === null || count($tasks) === 0) {
            return [];
        }

        return array_map(function ($task) {
            return $task['id'] ?? null;
        }, $tasks);
    }

    public function hasDuplicateTaskIDs(): bool
    {
        $ids = $this->getTaskIDs();
        return count(array_unique($ids)) !== count($ids);
    }

    public function hasTasksWithoutPackage(): bool
    {
        $tasks = $this->project->getTasks();
        if ($tasks === null || count($tasks) === 0) {
            return false;
        }

        $ids = $this->getWorkPackageIDs();
        foreach($this->project->getTasks() as $task) {
            if (empty($task['workPackage'])) {
                return true;
            }

            // a workPackage is set but no longer exists -> fail
            if (!in_array($task['workPackage'], $ids)) {
                return true;
            }
        }

        return false;
    }

    public function hasWorkPackages() : bool
    {
        $wp = $this->project->getWorkPackages();
        return $wp !== null && count($wp) > 0;
    }

    public function getMaxMonthFromTasks(): int
    {
        $max = 0;

        $tasks = $this->project->getTasks();
        if ($tasks === null || count($tasks) === 0) {
            return $max;
        }

        foreach($tasks as $task) {
            $months = $task['months'] ?? [];
            $new = max($months);
            if ($new > $max) {
                $max = $new;
            }
        }

        return $max;
    }

    public function getWorkPackageIDs(): array
    {
        $wp = $this->project->getWorkPackages();
        if ($wp === null || count($wp) === 0) {
            return [];
        }

        return array_map(function ($package) {
            return $package['id'] ?? null;
        }, $wp);
    }

    public function getWorkPackageNames(): array
    {
        $wp = $this->project->getWorkPackages();
        if ($wp === null || count($wp) === 0) {
            return [];
        }

        return array_map(function ($package) {
            return $package['name'] ?? null;
        }, $wp);
    }

    public function getWorkPackageIDsFromTaks(): array
    {
        $tasks = $this->project->getTasks();
        if ($tasks === null || count($tasks) === 0) {
            return [];
        }

        return array_map(function ($task) {
            return $task['workPackage'] ?? null;
        }, $tasks);
    }

    public function hasDuplicatePackageIDs(): bool
    {
        $ids = $this->getWorkPackageIDs();
        return count(array_unique($ids)) !== count($ids);
    }

    public function hasDuplicatePackageNames(): bool
    {
        $names = $this->getWorkPackageNames();
        return count(array_unique($names)) !== count($names);
    }

    public function hasPackageWithoutTasks(): bool
    {
        $wp = $this->project->getWorkPackages();
        if ($wp === null || count($wp) === null) {
            return false;
        }

        $ids = $this->getWorkPackageIDsFromTaks();
        foreach($wp as $package) {
            if (!in_array($package['id'], $ids)) {
                return true;
            }
        }

        return false;
    }
}
