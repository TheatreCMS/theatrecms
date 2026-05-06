<?php

namespace TheatreCMS\Controllers;

use TheatreCMS\Repositories\UserRepository;
use Delight\Auth\Auth;
use Delight\Auth\AuthError;
use Delight\Auth\EmailNotVerifiedException;
use Delight\Auth\InvalidEmailException;
use Delight\Auth\InvalidPasswordException;
use Delight\Auth\SecondFactorRequiredException;
use Delight\Auth\TooManyRequestsException;
use Delight\Auth\UserAlreadyExistsException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class LoginController extends BaseController
{
    private const CSRF_NAME_KEY  = 'csrf_name';
    private const CSRF_VALUE_KEY = 'csrf_value';

    private Auth $auth;

    public function __construct(UserRepository $repository, Twig $twig, Auth $auth)
    {
        $this->repository = $repository;
        $this->twig = $twig;
        $this->auth = $auth;
    }

    public function login(Request $request, Response $response): Response
    {
        try {
            $csrfNameKey  = $request->getAttribute(self::CSRF_NAME_KEY);
            $csrfValueKey = $request->getAttribute(self::CSRF_VALUE_KEY);

            return $this->twig->render($response, 'admin/login.html.twig', [
                'csrf_name_key'  => self::CSRF_NAME_KEY,
                'csrf_name'      => $csrfNameKey,
                'csrf_value_key' => self::CSRF_VALUE_KEY,
                'csrf_value'     => $csrfValueKey,
            ]);
        } catch (LoaderError|RuntimeError|SyntaxError $e) {
            $response->getBody()->write('An error occurred while loading the login page.');
            return $response->withStatus(500);
        }
    }

    public function authenticate(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        try {
            $this->auth->login($data['email'], $data['password']);

            return $response->withHeader('Location', '/admin')->withStatus(302);
        }
        catch (InvalidEmailException|AuthError|InvalidPasswordException|EmailNotVerifiedException|TooManyRequestsException|SecondFactorRequiredException $e) {
            return $response->withHeader('Location', '/admin/login')->withStatus(302);
        }
    }

    public function logout(Request $request, Response $response): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        session_destroy();
        return $response->withHeader('Location', '/admin/login')->withStatus(302);
    }

    public function register(Request $request, Response $response): Response
    {
        $data = $this->parseArgs($request->getParsedBody(), [
            'email' => '',
            'password' => '',
        ]);

        try {
            $this->auth->register($data['email'], $data['password']);
            return $response->withHeader('Location', '/admin/users');
        }
        catch (UserAlreadyExistsException|InvalidEmailException|AuthError|InvalidPasswordException|TooManyRequestsException $e) {
            return $response->withHeader('Location', '/admin/register');
        }
    }
}
