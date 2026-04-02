<?php

namespace TheatreCMS\Controllers;

use Delight\Auth\Auth;
use Delight\Auth\UnknownIdException;
use TheatreCMS\Repositories\UserRepository;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;

class UsersController extends BaseController
{
    private Auth $auth;

    public function __construct(UserRepository $repository, Twig $twig, Auth $auth)
    {
        $this->repository = $repository;
        $this->twig       = $twig;
        $this->auth       = $auth;
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/users/index.html.twig', [
            'users'         => $this->repository->fetchAll(),
            'currentUserId' => $this->auth->getUserId(),
        ]);
    }

    public function create(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/users/create.html.twig');
    }

    public function edit(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/users/edit.html.twig', [
            'user' => $this->repository->fetch($args['id']),
        ]);
    }

    public function destroy(Request $request, Response $response, array $args = []): Response
    {
        try {
            $id = (int) ($args['id'] ?? 0);
            $this->auth->admin()->deleteUserById($id);

            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render($response, 'admin/users/_table.html.twig', [
                    'users' => $this->repository->fetchAll(),
                ]);
            }

            return $response->withHeader('Location', '/admin/users')->withStatus(302);
        } catch (UnknownIdException $e) {
            $response->getBody()->write($e->getMessage());
            return $response->withStatus(400);
        }
    }

    public function store(Request $request, Response $response, array $args = []): Response
    {
        $isHtmx = (bool) $request->getHeaderLine('HX-Request');
        $body = $request->getParsedBody();

        if (empty($body)) {
            if ($isHtmx) {
                return $this->freshAlertResponse($response, 'error', 'Unable to create user. Please check your input.');
            }
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $required = ['email', 'username', 'password'];

        $data = $this->parseArgs($body, [
            'email'    => null,
            'username' => null,
            'password' => null,
            'role'     => 'user',
        ]);

        foreach ($required as $index) {
            if (empty($data[$index])) {
                if ($isHtmx) {
                    return $this->freshAlertResponse($response, 'error', 'Unable to create user. Please check your input.');
                }
                throw new \InvalidArgumentException("$index is required");
            }
        }

        $this->repository->create($data);

        if ($isHtmx) {
            return $this->freshAlertResponse($response, 'success', 'User created successfully.');
        }

        return $response->withHeader('Location', '/admin/users');
    }

    public function update(Request $request, Response $response, array $args = []): Response
    {
        $isHtmx = (bool) $request->getHeaderLine('HX-Request');
        $data = $request->getParsedBody();

        if (empty($data)) {
            if ($isHtmx) {
                return $this->freshAlertResponse($response, 'error', 'Unable to save user. Please check your input.');
            }
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

        $userId = intval($data['userId']);
        $user = $this->repository->fetch($userId);

        if (is_null($user)) {
            if ($isHtmx) {
                return $this->freshAlertResponse($response, 'error', 'Unable to save user. Please check your input.');
            }
            return $response->withStatus(404);
        }

        if (empty(trim((string)$data['email']))) {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address.';
        }

        if (empty(trim((string)$data['username']))) {
            $errors['username'] = 'Username is required.';
        }

        if (!empty($data['password']) || !empty($data['password_confirmation'])) {
            if ($data['password'] !== $data['password_confirmation']) {
                $errors['password_confirmation'] = 'Passwords do not match.';
            }
            if (empty($data['password'])) {
                $errors['password'] = 'Password is required when changing password.';
            }
        }

        $existingByEmail = $this->repository->findByEmail($data['email']);
        if ($existingByEmail && $existingByEmail->getId() !== $userId) {
            $errors['email'] = 'A user with that email already exists.';
        }

        if (method_exists($this->repository, 'findByUsername')) {
            $existingByUsername = $this->repository->findByUsername($data['username']);
            if ($existingByUsername && $existingByUsername->getId() !== $userId) {
                $errors['username'] = 'Username is already taken.';
            }
        }

        if (!empty($errors)) {
            if ($isHtmx) {
                $firstError = reset($errors);
                return $this->freshAlertResponse($response, 'error', $firstError);
            }
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

        try {
            $user->setEmail($data['email'])
                ->setUsername($data['username']);

            if (!empty($data['password'])) {
                $hashed = password_hash($data['password'], PASSWORD_DEFAULT);
                if (method_exists($user, 'setPassword')) {
                    $user->setPassword($hashed);
                }
            }

            $this->repository->update($user);
        } catch (\Throwable $e) {
            $errors['general'] = 'Failed to update user.';
            if ($isHtmx) {
                return $this->freshAlertResponse($response, 'error', 'Unable to save user. Please check your input.');
            }
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

        if ($isHtmx) {
            return $this->freshAlertResponse($response, 'success', 'User saved successfully.');
        }

        return $response->withHeader('Location', '/admin/users')->withStatus(302);
    }

    private function freshAlertResponse(Response $response, string $type, string $message): Response
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $this->twig->fetch('admin/partials/_alert.html.twig', [
            'type'    => $type,
            'message' => $message,
        ]));
        rewind($stream);
        return $response->withBody(new \Slim\Psr7\Stream($stream));
    }
}
