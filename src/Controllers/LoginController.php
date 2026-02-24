<?php

namespace Clubdeuce\TheatreCMS\Controllers;

use Clubdeuce\TheatreCMS\Repositories\UserRepository;
use Delight\Auth\AttemptCancelledException;
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
    private Twig $twig;
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
            return $this->twig->render($response, 'admin/login.html.twig');
        } catch (LoaderError|RuntimeError|SyntaxError $e) {
            $response->getBody()->write('An error occurred while loading the login page.');
            return $response->withStatus(500);
        }
    }

    public function authenticate(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
//        $email = $data['email'] ?? '';
//        $password = $data['password'] ?? '';
//
//        /** @var UserRepository $repository */
//        $repository = $this->repository();
//        $user = $repository->findByEmail($email);
//
//        if ($user && password_verify($password, $user->getPasswordHash())) {
//            if (session_status() !== PHP_SESSION_ACTIVE) {
//                session_start();
//            }
//            $_SESSION['user_id'] = $user->getId();
//            return $response->withHeader('Location', '/admin')->withStatus(302);
//        }
//
//        return $this->twig->render($response, 'admin/login.html.twig', [
//            'error' => 'Invalid email or password',
//            'email' => $email
//        ]);

        try {
            $this->auth->login($data['email'], $data['password']);

            return $response->withHeader('Location', '/admin')->withStatus(302);
        }
        catch (InvalidEmailException $e) {
            die('Wrong email address');
        }
        catch (InvalidPasswordException $e) {
            die('Wrong password');
        }
        catch (EmailNotVerifiedException $e) {
            die('Email not verified');
        }
        catch (\Delight\Auth\TooManyRequestsException $e) {
            die('Too many requests');
        } catch (AttemptCancelledException $e) {
        } catch (AuthError $e) {
        } catch (SecondFactorRequiredException $e) {
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
}
