<?php
declare(strict_types=1);

namespace App\Entity\Listener;

use App\Entity\Process;
use App\Entity\UserObjectRole;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class ProcessListener
{
    public function preRemove(Process $process, LifecycleEventArgs $args)
    {
        $em = $args->getObjectManager();
        $repo = $em->getRepository(UserObjectRole::class);
        $roles = $repo->findBy([
            'objectId'   => $process->getId(),
            'objectType' => Process::class,
        ]);
        foreach ($roles as $role) {
            $em->remove($role);
        }
    }
}
