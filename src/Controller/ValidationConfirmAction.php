<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Validation;
use App\Event\ValidationConfirmedEvent;
use App\Event\ValidationExpiredEvent;
use App\Event\ValidationNotFoundEvent;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ValidationConfirmAction
{
    public function __invoke(
        Validation $data,
        Request $request,
        EventDispatcherInterface $dispatcher,
        ManagerRegistry $registry
    ) {
        // the DTO in $data was validated by the DataTransformer
        // so the token has the correct format
        $params = json_decode($request->getContent(), true);

        if ($params['token'] !== $data->getToken()) {
            // trigger an event to allow blocking the requesting IP/user after
            // x failed attempts
            $dispatcher->dispatch(new ValidationNotFoundEvent());

            // no special message, same as with unknown/invalid ID
            throw new NotFoundHttpException('Not Found');
        }

        $em = $registry->getManagerForClass(Validation::class);

        if ($data->isExpired()) {
            // trigger an event to allow logging, blocking the requesting
            // IP/user after x failed attempts or deleting the expired user etc.
            $dispatcher->dispatch(new ValidationExpiredEvent($data));

            // the listeners don't need to remove the validation themselves,
            // also they shouldn't flush the EM. When they are called via the
            // purge event the messageHandler will remove & flush.
            $em->remove($data);
            $em->flush();

            throw new BadRequestHttpException('Validation is expired.');
        }

        unset($params['token']);

        // trigger follow-up actions e.g. for setting the validated flag on the
        // confirmed user account or changing the email address
        $dispatcher->dispatch(new ValidationConfirmedEvent($data, $params));

        // the listeners don't need to remove the validation themselves,
        // also they shouldn't flush the EM.
        $em->remove($data);
        $em->flush();

        // return 205: the server has fulfilled the request and the user agent
        // SHOULD reset the document view
        return new JsonResponse([
            'success' => true,
            'message' => 'Validation successful',
        ], Response::HTTP_RESET_CONTENT);
    }
}
