<?php

namespace Clubdeuce\TheatreCMS\Controllers;

use Clubdeuce\TheatreCMS\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class LoginController extends BaseController
{
    private Twig $twig;

    public function __construct(UserRepository $repository, Twig $twig)
    {
        $this->repository = $repository;
        $this->twig = $twig;
    }

    public function login(Request $request, Response $response): Response
    {
        return $this->twig->render($response, 'admin/login.html.twig');
    }

    public function authenticate(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        /** @var UserRepository $repository */
        $repository = $this->repository();
        $user = $repository->findByEmail($email);

        if ($user && password_verify($password, $user->getPasswordHash())) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $_SESSION['user_id'] = $user->getId();
            return $response->withHeader('Location', '/admin')->withStatus(302);
        }

        return $this->twig->render($response, 'admin/login.html.twig', [
            'error' => 'Invalid email or password',
            'email' => $email
        ]);
    }

    public function logout(Request $request, Response $response): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        session_destroy();
        return $response->withHeader('Location', '/admin/login')->withStatus(302);
    }
}
