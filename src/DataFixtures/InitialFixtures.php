<?php
declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

class InitialFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['initial'];
    }

    public function load(ObjectManager $manager)
    {
        //$manager->flush();
    }
}