<?php
declare(strict_types=1);

namespace App\Entity\Listener;

use App\Entity\Fund;
use App\Entity\UserObjectRole;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class FundListener
{
    public function preRemove(Fund $process, LifecycleEventArgs $args)
    {
        $em = $args->getObjectManager();
        $repo = $em->getRepository(UserObjectRole::class);
        $roles = $repo->findBy([
            'objectId'   => $process->getId(),
            'objectType' => Fund::class,
        ]);
        foreach ($roles as $role) {
            $em->remove($role);
        }
    }
}
