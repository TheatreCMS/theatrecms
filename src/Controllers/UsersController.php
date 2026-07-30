<?php

namespace TheatreCMS\Controllers;

use Delight\Auth\Auth;
use Delight\Auth\UnknownIdException;
use TheatreCMS\Repositories\UserRepository;
use TheatreCMS\Models\User;
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
        return $this->twig->render(
            $response,
            'admin/users/index.html.twig',
            $this->buildUsersListViewData($request)
        );
    }

    public function create(Request $request, Response $response, array $args = []): Response
    {
        return $this->twig->render($response, 'admin/users/create.html.twig', [
            'old' => ['role' => 'user'],
        ]);
    }

    public function edit(Request $request, Response $response, array $args = []): Response
    {
        $user = $this->repository->fetch((int) $args['id']);

        if (!$user instanceof User) {
            return $response->withStatus(404);
        }

        return $this->twig->render($response, 'admin/users/edit.html.twig', [
            'user' => $user,
            'userRole' => $this->repository->resolveRoleLabel($user),
            'currentUserId' => $this->auth->getUserId(),
        ]);
    }

    public function destroy(Request $request, Response $response, array $args = []): Response
    {
        try {
            $id = (int) ($args['id'] ?? 0);
            $this->auth->admin()->deleteUserById($id);

            if ($request->getHeaderLine('HX-Request')) {
                return $this->twig->render(
                    $response,
                    'admin/users/_list.html.twig',
                    $this->buildUsersListViewData($request)
                );
            }

            return $this->buildListRedirect($response, $request, '/admin/users')->withStatus(302);
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
            'email' => null,
            'username' => null,
            'password' => null,
            'password_confirmation' => null,
            'role' => 'user',
        ]);

        $errors = [];

        foreach ($required as $index) {
            if (empty($data[$index])) {
                $errors[$index] = ucfirst($index) . ' is required.';
            }
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address.';
        }

        if (($data['password'] ?? '') !== ($data['password_confirmation'] ?? '')) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }

        if (!$this->isValidRole((string) $data['role'])) {
            $errors['role'] = 'Invalid role selected.';
        }

        $existingByEmail = !empty($data['email']) ? $this->repository->findByEmail((string) $data['email']) : null;
        if ($existingByEmail instanceof User) {
            $errors['email'] = 'A user with that email already exists.';
        }

        $existingByUsername = !empty($data['username']) ? $this->repository->findByUsername((string) $data['username']) : null;
        if ($existingByUsername instanceof User) {
            $errors['username'] = 'Username is already taken.';
        }

        if (!empty($errors)) {
            if ($isHtmx) {
                return $this->freshAlertResponse($response, 'error', (string) reset($errors));
            }

            return $this->twig->render($response, 'admin/users/create.html.twig', [
                'errors' => $errors,
                'old' => $data,
            ]);
        }

        try {
            $this->repository->create($data);
        } catch (\Throwable $e) {
            if ($isHtmx) {
                return $this->freshAlertResponse($response, 'error', 'Unable to create user. Please check your input.');
            }

            return $this->twig->render($response, 'admin/users/create.html.twig', [
                'errors' => ['general' => 'Unable to create user. Please check your input.'],
                'old' => $data,
            ]);
        }

        if ($isHtmx) {
            return $this->freshAlertResponse($response, 'success', 'User created successfully.');
        }

        return $response->withHeader('Location', '/admin/users')->withStatus(302);
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

        if (!$user instanceof User) {
            if ($isHtmx) {
                return $this->freshAlertResponse($response, 'error', 'Unable to save user. Please check your input.');
            }
            return $response->withStatus(404);
        }

        $email = trim((string) $data['email']);
        $username = trim((string) $data['username']);
        $role = (string) $data['role'];

        if ($email === '') {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address.';
        }

        if ($username === '') {
            $errors['username'] = 'Username is required.';
        }

        if (!$this->isValidRole($role)) {
            $errors['role'] = 'Invalid role selected.';
        }

        if ($userId === $this->auth->getUserId() && $role !== 'admin') {
            $errors['role'] = 'You cannot remove your own admin role.';
        }

        if (!empty($data['password']) || !empty($data['password_confirmation'])) {
            if ($data['password'] !== $data['password_confirmation']) {
                $errors['password_confirmation'] = 'Passwords do not match.';
            }
            if (empty($data['password'])) {
                $errors['password'] = 'Password is required when changing password.';
            }
        }

        $existingByEmail = $this->repository->findByEmail($email);
        if ($existingByEmail && $existingByEmail->getId() !== $userId) {
            $errors['email'] = 'A user with that email already exists.';
        }

        $existingByUsername = $this->repository->findByUsername($username);
        if ($existingByUsername && $existingByUsername->getId() !== $userId) {
            $errors['username'] = 'Username is already taken.';
        }

        if (!empty($errors)) {
            if ($isHtmx) {
                $firstError = reset($errors);
                return $this->freshAlertResponse($response, 'error', $firstError);
            }
            try {
                return $this->twig->render($response, 'admin/users/edit.html.twig', [
                    'user' => $user,
                    'userRole' => $this->repository->resolveRoleLabel($user),
                    'currentUserId' => $this->auth->getUserId(),
                    'errors' => $errors,
                    'old' => $data,
                ]);
            } catch (\Throwable $e) {
                return $response->withStatus(500);
            }
        }

        try {
            $user->setEmail($email)
                ->setUsername($username);

            $this->repository->update($user);
            $this->repository->syncRoleByUserId($userId, $role);

            if (!empty($data['password'])) {
                $this->repository->updatePassword($userId, (string) $data['password']);
            }
        } catch (\Throwable $e) {
            $errors['general'] = 'Failed to update user.';
            if ($isHtmx) {
                return $this->freshAlertResponse($response, 'error', 'Unable to save user. Please check your input.');
            }
            try {
                return $this->twig->render($response, 'admin/users/edit.html.twig', [
                    'user' => $user,
                    'userRole' => $this->repository->resolveRoleLabel($user),
                    'currentUserId' => $this->auth->getUserId(),
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

    /**
     * @return array<string, mixed>
     */
    private function buildUsersListViewData(Request $request): array
    {
        $data = $this->buildPaginatedViewData(
            $request,
            $this->repository,
            'users',
            '/admin/users',
            ['currentUserId' => $this->auth->getUserId()]
        );

        $roleMap = [];
        foreach ($data['users'] as $user) {
            if ($user instanceof User) {
                $roleMap[$user->getId()] = $this->repository->resolveRoleLabel($user);
            }
        }

        $data['userRoles'] = $roleMap;

        return $data;
    }

    private function isValidRole(string $role): bool
    {
        return in_array($role, ['user', 'admin'], true);
    }
}
