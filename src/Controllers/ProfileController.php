<?php

namespace TheatreCMS\Controllers;

use Delight\Auth\Auth;
use Delight\Auth\InvalidPasswordException;
use TheatreCMS\Repositories\UserRepository;
use TheatreCMS\Models\User;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

class ProfileController extends BaseController
{
    private Auth $auth;

    public function __construct(UserRepository $repository, Twig $twig, Auth $auth)
    {
        $this->repository = $repository;
        $this->twig       = $twig;
        $this->auth       = $auth;
    }

    public function edit(Request $request, Response $response, array $args = []): Response
    {
        $user = $this->repository->fetch($this->auth->getUserId());

        if (!$user instanceof User) {
            return $response->withStatus(404);
        }

        return $this->twig->render($response, 'admin/profile/edit.html.twig', [
            'user' => $user,
        ]);
    }

    public function update(Request $request, Response $response, array $args = []): Response
    {
        $isHtmx = (bool) $request->getHeaderLine('HX-Request');
        $body = $request->getParsedBody();

        if (empty($body)) {
            if ($isHtmx) {
                return $this->freshAlertResponse($response, 'error', 'Unable to save profile. Please check your input.');
            }
            return $response->withStatus(400);
        }

        $user = $this->repository->fetch($this->auth->getUserId());

        if (!$user instanceof User) {
            return $response->withStatus(404);
        }

        $data = $this->parseArgs($body, [
            'email' => null,
            'current_password' => null,
            'password' => null,
            'password_confirmation' => null,
        ]);

        $errors = [];

        $email = trim((string) $data['email']);

        if ($email === '') {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address.';
        }

        if (!empty($data['password']) || !empty($data['password_confirmation'])) {
            if (empty($data['current_password'])) {
                $errors['current_password'] = 'Enter your current password to set a new one.';
            }
            if ($data['password'] !== $data['password_confirmation']) {
                $errors['password_confirmation'] = 'Passwords do not match.';
            }
            if (empty($data['password'])) {
                $errors['password'] = 'Password is required when changing password.';
            }
        }

        $existingByEmail = $this->repository->findByEmail($email);
        if ($existingByEmail && $existingByEmail->getId() !== $user->getId()) {
            $errors['email'] = 'A user with that email already exists.';
        }

        if (!empty($errors)) {
            if ($isHtmx) {
                return $this->freshAlertResponse($response, 'error', (string) reset($errors));
            }

            return $this->twig->render($response, 'admin/profile/edit.html.twig', [
                'user' => $user,
                'errors' => $errors,
                'old' => $data,
            ]);
        }

        try {
            if (!empty($data['password'])) {
                $this->auth->changePassword((string) $data['current_password'], (string) $data['password']);
            }

            $this->repository->updateEmail($user->getId(), $email);
        } catch (InvalidPasswordException $e) {
            $errors['current_password'] = 'Your current password is incorrect.';

            if ($isHtmx) {
                return $this->freshAlertResponse($response, 'error', $errors['current_password']);
            }

            return $this->twig->render($response, 'admin/profile/edit.html.twig', [
                'user' => $user,
                'errors' => $errors,
                'old' => $data,
            ]);
        }

        if ($isHtmx) {
            return $this->freshAlertResponse($response, 'success', 'Profile saved successfully.');
        }

        return $response->withHeader('Location', '/admin/profile')->withStatus(302);
    }
}
