<?php

namespace Clubdeuce\TheatreCMS\Controllers;

use Clubdeuce\TheatreCMS\Repositories\UserRepository;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;


class UsersController extends BaseController
{
    private Twig $twig;

    public function __construct(UserRepository $repository, Twig $twig)
    {
        $this->repository = $repository;
        $this->twig = $twig;
    }

    public function store(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        if (empty($body))
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);

        $required = ['email', 'username', 'password'];

        $data = $this->parseArgs($body,[
            'email'    => null,
            'username' => null,
            'password' => null,
            'role'     => 'user',
        ]);

        foreach($required as $index) {
            if (empty($data[$index])) {
                throw new \InvalidArgumentException("$index is required");
            }
        }

        $this->repository->create($data);

        return $response->withHeader('Location', '/admin/users');
    }

    public function update(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (empty($data)) {
            return $response->withStatus(400);
        }

        $data = $this->parseArgs($data, [
            'userId' => 0,
            'email' => null,
            'username' => null,
            'password' => null,
            'password_confirmation' => null,
            'role' => 'user',
        ]);

        $errors = [];

        // Ensure user exists
        $userId = intval($data['userId']);
        $user = $this->repository->fetch($userId);

        if (is_null($user)) {
            return $response->withStatus(404);
        }

        // Basic required field checks
        if (empty(trim((string)$data['email']))) {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address.';
        }

        if (empty(trim((string)$data['username']))) {
            $errors['username'] = 'Username is required.';
        }

        // Password confirmation (only validate if password provided)
        if (!empty($data['password']) || !empty($data['password_confirmation'])) {
            if ($data['password'] !== $data['password_confirmation']) {
                $errors['password_confirmation'] = 'Passwords do not match.';
            }
            if (empty($data['password'])) {
                $errors['password'] = 'Password is required when changing password.';
            }
        }

        // Uniqueness checks
        $existingByEmail = $this->repository->findByEmail($data['email']);
        if ($existingByEmail && $existingByEmail->getId() !== $userId) {
            $errors['email'] = 'A user with that email already exists.';
        }

        // repository may implement findByUsername; if available, use it
        if (method_exists($this->repository, 'findByUsername')) {
            $existingByUsername = $this->repository->findByUsername($data['username']);
            if ($existingByUsername && $existingByUsername->getId() !== $userId) {
                $errors['username'] = 'Username is already taken.';
            }
        }

        if (!empty($errors)) {
            // Render the edit template with errors and old input
            try {
                return $this->twig->render($response, 'admin/users/edit.html.twig', [
                    'user' => $user,
                    'errors' => $errors,
                    'old' => $data,
                ]);
            } catch (\Throwable $e) {
                return $response->withStatus(500);
            }
        }

        // Apply updates
        try {
            $user->setEmail($data['email'])
                ->setUsername($data['username']);

            if (!empty($data['password'])) {
                // Hash password before storing
                $hashed = password_hash($data['password'], PASSWORD_DEFAULT);
                // If model exposes setPassword/ setPasswordHash, use that; here setPassword accepts raw string
                if (method_exists($user, 'setPassword')) {
                    $user->setPassword($hashed);
                }
            }

            $this->repository->update($user);
        } catch (\Throwable $e) {
            // On failure, render edit with a generic error
            $errors['general'] = 'Failed to update user.';
            try {
                return $this->twig->render($response, 'admin/users/edit.html.twig', [
                    'user' => $user,
                    'errors' => $errors,
                    'old' => $data,
                ]);
            } catch (\Throwable $e) {
                return $response->withStatus(500);
            }
        }

        return $response->withHeader('Location', '/admin/users')->withStatus(302);
    }

}
