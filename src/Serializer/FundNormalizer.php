<?php
declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Fund;
use App\Entity\UserObjectRole;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

class FundNormalizer implements ContextAwareNormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'FUND_NORMALIZER_ALREADY_CALLED';

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * {@inheritDoc}
     *
     * @param Fund $object
     */
    public function normalize($object, $format = null, array $context = [])
    {
        $token = $this->tokenStorage->getToken();
        if ($token && $token->getUser() instanceof UserInterface) {
            /* @var $currentUser \App\Entity\User */
            $currentUser = $token->getUser();

            foreach ($currentUser->getObjectRoles() as $role) {
                if ($role->getRole() !== UserObjectRole::ROLE_JURY_MEMBER) {
                    continue;
                }

                if ($role->getObjectType() !== Fund::class) {
                    continue;
                }

                if ($role->getObjectId() == $object->getId()) {
                    $context['groups'][] = 'fund:juror-read';
                }
            }
        }

        $context[self::ALREADY_CALLED] = true;

        return $this->normalizer->normalize($object, $format, $context);
    }

    public function supportsNormalization($data, $format = null, array $context = [])
    {
        // Make sure we're not called twice
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof Fund;
    }
}
