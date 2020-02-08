<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\FundApplication;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;

class SubmitFundApplicationAction
{
    // @todo
    // * Bestätigungsemail an projekt team
    // * Daten sichern
    public function __invoke(
        Request $request,
        FundApplication $data,
        ManagerRegistry $registry
    ) {
        $data->setState(FundApplication::STATE_SUBMITTED);
        $em = $registry->getManagerForClass(FundApplication::class);
        $em->flush();

        // return the updated fundApplication
        return $data;
    }
}
