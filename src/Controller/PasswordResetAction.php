<?php
declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Entity\User;
use App\Message\UserForgotPasswordMessage;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;

class PasswordResetAction
{
    public function __invoke(
        Request $request,
        ManagerRegistry $registry,
        MessageBusInterface $bus,
        Security $security,
        ValidatorInterface $validator
    ) {
        if ($security->isGranted(User::ROLE_USER)) {
            throw new AccessDeniedException('Forbidden for authenticated users.');
        }

        // DTO was validated by the DataTransformer, username & validationUrl
        // should be there
        $params = json_decode($request->getContent(), true);

        $entityManager = $registry->getManagerForClass(User::class);
        $user = $entityManager->getRepository(User::class)
            ->loadUserByUsername($params['username']);

        // @todo
        // * don't give information about existing usernames/emails and return
        //   a success message instead?
        // * trigger an event to allow logging & blocking IP/User after to many
        //   attempts
        if (!$user || !$user->isActive()) {
            throw new NotFoundHttpException('No matching user found.');
        }

        $bus->dispatch(
            new UserForgotPasswordMessage($user->getId(), $params['validationUrl'])
        );

        // return 202: the action has not yet been enacted
        return new JsonResponse([
            'success' => true,
            'message' => 'Request received',
        ], Response::HTTP_ACCEPTED);
    }
}
