<?php
declare(strict_types=1);

namespace App\Serializer;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use App\Entity\Project;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareDenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;

class ProjectDenormalizer implements
    ContextAwareDenormalizerInterface,
    DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    private const ALREADY_CALLED = 'PROJECT_DENORMALIZER_ALREADY_CALLED';

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @inheritDoc
     */
    public function supportsDenormalization($data, $type, $format = null, array $context = [])
    {
        // Make sure we're not called twice
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $type === Project::class
            && isset($context[AbstractItemNormalizer::OBJECT_TO_POPULATE]);
    }

    /**
     * {@inheritDoc}
     *
     * @param Project $data
     */
    public function denormalize($data, $type, $format = null, array $context = [])
    {
        $project = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE];
        $token = $this->tokenStorage->getToken();
        if ($token && $token->getUser() instanceof UserInterface) {
            $currentUser = $token->getUser();

            if ($project->userIsOwner($currentUser))
            {
                $context['groups'][] = 'project:owner-write';
            }

            if ($project->userIsMember($currentUser))
            {
                $context['groups'][] = 'project:member-write';
            }

            if ($context['operation_type'] === 'item'
                && $context['item_operation_name'] === "put"
            ) {
                if ($project->userIsOwner($currentUser))
                {
                    $context['groups'][] = 'project:owner-update';
                }

                if ($project->userIsMember($currentUser))
                {
                    $context['groups'][] = 'project:member-update';
                }
            }
        }

        $context[self::ALREADY_CALLED] = true;

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }
}
