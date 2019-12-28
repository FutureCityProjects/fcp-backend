<?php

namespace App\PHPUnit\Mailer;

use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Custom transport that prevents the usage of TLS/STARTTLS for testing on
 * machines where the SMTP connection is intercepted by antivirus software,
 * which causes the certificate check to fail as there is no config option to
 * disable this check.
 */
final class NoTlsTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $port = $dsn->getPort(0);
        $host = $dsn->getHost();

        $transport = new NoTlsTransport($host, $port, false, $this->dispatcher, $this->logger);

        if ($user = $dsn->getUser()) {
            $transport->setUsername($user);
        }

        if ($password = $dsn->getPassword()) {
            $transport->setPassword($password);
        }

        return $transport;
    }

    protected function getSupportedSchemes(): array
    {
        return ['notls'];
    }
}
