<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The actual authentication is handled upstream by the `login` firewall's
 * json_login listener (see config/packages/security.yaml): it intercepts
 * POST /api/auth/login before the request ever reaches a controller and
 * returns the JWT (success_handler) or a 401 (failure_handler).
 *
 * This route only needs to exist so Symfony's router resolves the path —
 * it is never actually executed.
 */
final class AuthController extends AbstractController
{
    #[Route('/api/auth/login', name: 'app_api_auth_login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('This should never be reached: intercepted by the json_login firewall listener.');
    }
}
