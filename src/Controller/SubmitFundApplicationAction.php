<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\FundApplication;
use App\Entity\Helper\FundApplicationHelper;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

class SubmitFundApplicationAction
{
    // @todo
    // * Bestätigungsemail an projekt team
    public function __invoke(
        Request $request,
        FundApplication $data,
        ManagerRegistry $registry,
        UserInterface $user
    ) {
        $helper = new FundApplicationHelper($data);
        $submission = $helper->getSubmissionData();
        $submission['submittedBy'] = [
            'userId'    => $user->getId(),
            'username'  => $user->getUsername(),
            'firstName' => $user->getFirstName(),
            'lastName'  => $user->getLastName(),
        ];

        $data->setSubmissionData($submission);
        $data->setState(FundApplication::STATE_SUBMITTED);
        $em = $registry->getManagerForClass(FundApplication::class);
        $em->flush();

        // return the updated fundApplication
        return $data;
    }
}
