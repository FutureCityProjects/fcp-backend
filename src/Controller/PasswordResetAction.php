<?php
declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Entity\User;
use App\Message\UserForgotPasswordMessage;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;

class PasswordResetAction
{
    /**
     * @var EventDispatcherInterface
     */
    private EventDispatcherInterface $dispatcher;

    private $entityManager;

    public function __construct(ManagerRegistry $registry, EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher    = $dispatcher;
        $this->entityManager = $registry->getManagerForClass(User::class);
    }

    public function __invoke(
        Request $request,
        User $data,
        EventDispatcherInterface $dispatcher,
        ManagerRegistry $registry,
        MessageBusInterface $bus,
        Security $security,
        ValidatorInterface $validator
    ) {
        if ($security->isGranted(User::ROLE_USER)) {
            throw new AccessDeniedException('Forbidden for authenticated users.');
        }

        $params = json_decode($request->getContent(), true);
        /*
        if (isset($params['username']) && isset($params['email'])) {
            throw new BadRequestHttpException('Only username OR email allowed.');
        }

        $identifier = $params['username'] ?? ($params['email'] ?? null);
        if (empty($identifier)) {
            throw new BadRequestHttpException('Username OR email required.');
        }
*/
        $entityManager = $registry->getManagerForClass(User::class);

        // DTO was validated by the DataTransformer, username should be there
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
            new UserForgotPasswordMessage($user->getId())
        );

        // return 202: the action has not yet been enacted
        return new JsonResponse([
            'success' => true,
            'message' => 'Request received',
        ], Response::HTTP_ACCEPTED);
    }
}
