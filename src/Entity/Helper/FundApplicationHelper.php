<?php
declare(strict_types=1);

namespace App\Entity\Helper;

use App\Entity\FundApplication;
use DateTimeImmutable;

class FundApplicationHelper
{
    protected FundApplication $application;

    public function __construct(FundApplication $application)
    {
        $this->application = $application;
    }

    /**
     * Returns the current state of the application and the project to be stored
     * as immutable submission data.
     * submittedBy must be filled in by the executing Action.
     *
     * @todo save all letters of intent, generate HTML file of the submission
     * data, notify all project members, generate zip-file for the jury?
     *
     * @return array
     * @throws \Exception
     */
    public function getSubmissionData()
    {
        $project = $this->application->getProject();

        $now = new DateTimeImmutable();
        $data = [
            'projectId'      => $project->getId(),
            'submissionDate' => $now->format(DateTimeImmutable::ATOM),

            // to be set by the SubmitApplicationAction
            'submittedBy' => [
                'userId'    => null,
                'username'  => null,
                'firstName' => null,
                'lastName'  => null,
            ],

            // profile data
            'name'             => $project->getName(),
            'shortDescription' => $project->getShortDescription(),
            'description'      => $project->getDescription(),
            'goal'             => $project->getGoal(),
            'challenges'       => $project->getChallenges(),
            'vision'           => $project->getVision(),
            'delimitation'     => $project->getDelimitation(),

            // plan data
            'implementationBegin'  => $project->getImplementationBegin()
                ? $project->getImplementationBegin()->format(DateTimeImmutable::ATOM)
                : "",
            'implementationTime'   => $project->getImplementationTime(),
            'impact'               => $project->getImpact(),
            'outcome'              => $project->getOutcome(),
            'targetGroups'         => $project->getTargetGroups(),
            'results'              => $project->getResults(),
            'utilization'          => $project->getUtilization(),
            'tasks'                => $project->getTasks(),
            'workPackages'         => $project->getWorkPackages(),
            'resourceRequirements' => $project->getResourceRequirements(),

            // application details
            'contactEmail'      => $project->getContactEmail(),
            'contactName'       => $project->getContactName(),
            'contactPhone'      => $project->getContactPhone(),
            'holderAddressInfo' => $project->getHolderAddressInfo(),
            'holderCity'        => $project->getHolderCity(),
            'holderName'        => $project->getHolderName(),
            'holderStreet'      => $project->getHolderStreet(),
            'holderZipCode'     => $project->getHolderZipCode(),

            // data stored in the application
            'concretizations' => $this->application->getConcretizations(),
            'requestedFunding' => $this->application->getRequestedFunding(),
        ];

        return $data;
    }
}
