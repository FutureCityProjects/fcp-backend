<?php
declare(strict_types=1);

namespace App\Entity\Listener;

use App\Entity\FundApplication;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class FundApplicationListener
{
    /**
     * Automatically update the state of the application when the team fills
     * in data.
     *
     * @param FundApplication $project
     * @param LifecycleEventArgs $args
     */
    public function preUpdate(FundApplication $application, LifecycleEventArgs $args)
    {
        if ($application->getState() === FundApplication::STATE_SUBMITTED) {
            return;
        }

        $application->setState(FundApplication::STATE_CONCRETIZATION);

        if ($application->getConcretizationSelfAssessment()
            !== FundApplication::SELF_ASSESSMENT_100_PERCENT
        ) {
            return;
        }

        // the fund has no concretizations -> nothing more to check
        $fundConcretizations = $application->getFund()->getConcretizations();
        if ($fundConcretizations->count() === 0) {
            $application->setState(FundApplication::STATE_DETAILING);
            return;
        }

        $concretizations = $application->getConcretizations();

        // no concretizations but the fund has some -> keep STATE_CONCRETIZATION
        if ($concretizations === null || count($concretizations) === 0) {
            return;
        }

        $concretizationIds = array_keys($concretizations);
        foreach($fundConcretizations as $concretization) {
            if (!in_array($concretization->getId(), $concretizationIds)) {
                // not all concretizations have been filled in
                // -> keep STATE_CONCRETIZATION
                return;
            }

            if (empty($concretizations[$concretization->getId()])) {
                // concretization has no content -> keep STATE_CONCRETIZATION
                return;
            }
        }

        $application->setState(FundApplication::STATE_DETAILING);
    }
}
