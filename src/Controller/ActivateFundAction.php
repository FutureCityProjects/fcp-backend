<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Fund;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ActivateFundAction
{
    // @todo Bestätigungsemail an PO?
    // Benachrichtigungsemail an alle Projekte?
    public function __invoke(
        Request $request,
        Fund $data,
        ManagerRegistry $registry
    ) {
        if (!$data->canBeActivated()) {
            throw new BadRequestHttpException(
                'validate.fund.activationNotPossible');
        }

        $data->setState(Fund::STATE_ACTIVE);
        $em = $registry->getManagerForClass(Fund::class);
        $em->flush();

        // return the updated fund
        return $data;
    }
}
