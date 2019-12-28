<?php
declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Entity\User;
use App\Event\UserRegisteredEvent;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class UserRegistrationAction
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

    public function __invoke(Request $request, User $data, ValidatorInterface $validator)
    {
        // we have to manually validate the entity,
        // the DTO was validated by the DataTransformer
        $validator->validate($data, ['groups' => ['Default', 'user:register']]);

        // save the user, we need his ID for the UserRegisteredEvent/-Message
        $this->entityManager->persist($data);
        $this->entityManager->flush();

        $params = json_decode($request->getContent(), true);

        // trigger an event to allow follow-up actions like sending
        // a validation email etc.
        $this->dispatcher->dispatch(
            new UserRegisteredEvent($data, $params['validationUrl'])
        );

        return $data;
    }
}
