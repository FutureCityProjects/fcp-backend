<?php
declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApplicationTest extends WebTestCase
{
    public function testGetIndex()
    {
        self::createClient()->request('GET', '/');

        $this->assertResponseStatusCodeSame(200);
    }

    public function testGetError()
    {
        self::createClient()->request('GET', '/error');

        $this->assertResponseStatusCodeSame(404);
    }
}
