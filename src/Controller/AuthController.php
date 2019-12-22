<?php
declare(strict_types=1);

namespace App\Controller;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{
    /**
     * @Route("/refresh_token", name="refresh_token")
     * @IsGranted("ROLE_USER")
     */
    public function refreshToken(JWTTokenManagerInterface $tokenManager)
    {
        $token = $tokenManager->create($this->getUser());
        return new JsonResponse(['token' => $token]);
    }
}
