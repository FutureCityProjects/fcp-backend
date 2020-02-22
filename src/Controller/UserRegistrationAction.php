<?php
declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Entity\User;
use App\Event\UserRegisteredEvent;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class UserRegistrationAction
{
    public function __invoke(
        Request $request,
        User $data,
        EventDispatcherInterface $dispatcher,
        ManagerRegistry $registry,
        ValidatorInterface $validator,
        ParameterBagInterface $parameterBag
    ) {
        // we have to manually validate the entity,
        // the DTO was validated by the DataTransformer
        $validator->validate($data, ['groups' => ['Default', 'user:register']]);

        // allow to skip validation via env configuration
        if (!$parameterBag->get('user.validation_required')) {
            $data->setIsValidated(true);
        }

        // save the user, we need his ID for the UserRegisteredEvent/-Message
        $entityManager = $registry->getManagerForClass(User::class);
        $entityManager->persist($data);
        $entityManager->flush();

        $params = json_decode($request->getContent(), true);

        // @todo no event, directly dispatch message? Event could be
        // thrown in the messagehandler
        // trigger an event to allow follow-up actions like sending
        // a validation email etc.
        $dispatcher->dispatch(
            new UserRegisteredEvent($data, $params['validationUrl'])
        );

        return $data;
    }
}
