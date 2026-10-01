<?php

namespace TheatreCMS\Console\Command;

use Delight\Auth\DuplicateUsernameException;
use Delight\Auth\InvalidEmailException;
use Delight\Auth\InvalidPasswordException;
use Delight\Auth\UserAlreadyExistsException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use TheatreCMS\Repositories\UserRepository;

/**
 * Creates the first administrator, so a fresh install needn't go through /admin/register. Safe to
 * re-run: once an admin exists it does nothing unless --force is passed. Options fall back to the
 * THEATRECMS_ADMIN_* environment variables, then to interactive prompts.
 */
#[AsCommand(name: 'user:create-admin', description: 'Create an administrator account')]
class UserCreateAdminCommand extends Command
{
    public function __construct(private readonly UserRepository $users)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Admin email (or THEATRECMS_ADMIN_EMAIL)')
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'Admin username (or THEATRECMS_ADMIN_USERNAME)')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Admin password (or THEATRECMS_ADMIN_PASSWORD)')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Create another admin even if one exists');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->users->hasAdminUser() && !$input->getOption('force')) {
            $output->writeln('An administrator already exists; nothing to do. Pass --force to create another one anyway.');
            return Command::SUCCESS;
        }

        $email = $this->value($input, $output, 'email', 'THEATRECMS_ADMIN_EMAIL', 'Admin email: ');
        if ($email === null) {
            return Command::FAILURE;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error($output, "Invalid email address: {$email}");
            return Command::FAILURE;
        }

        $username = $this->value($input, $output, 'username', 'THEATRECMS_ADMIN_USERNAME', 'Admin username: ');
        if ($username === null) {
            return Command::FAILURE;
        }

        $password = $this->password($input, $output);
        if ($password === null) {
            return Command::FAILURE;
        }

        try {
            $userId = $this->users->create([
                'email' => $email,
                'username' => $username,
                'password' => $password,
                'role' => 'admin',
            ]);
        } catch (UserAlreadyExistsException) {
            $this->error($output, 'A user with that email already exists.');
            return Command::FAILURE;
        } catch (DuplicateUsernameException) {
            $this->error($output, 'That username is already taken.');
            return Command::FAILURE;
        } catch (InvalidEmailException) {
            $this->error($output, 'Invalid email address.');
            return Command::FAILURE;
        } catch (InvalidPasswordException) {
            $this->error($output, 'Invalid password (it cannot be empty).');
            return Command::FAILURE;
        }

        $output->writeln("Created admin user #{$userId} ({$email}).");

        return Command::SUCCESS;
    }

    private function value(InputInterface $input, OutputInterface $output, string $option, string $env, string $prompt): ?string
    {
        $value = $input->getOption($option) ?? (getenv($env) ?: null);
        if ($value !== null) {
            return trim((string) $value);
        }

        if (!$input->isInteractive()) {
            $this->error($output, "Missing admin {$option}. Pass --{$option}=... or set {$env}.");
            return null;
        }

        return trim((string) $this->questionHelper()->ask($input, $output, new Question($prompt)));
    }

    private function password(InputInterface $input, OutputInterface $output): ?string
    {
        $password = $input->getOption('password') ?? (getenv('THEATRECMS_ADMIN_PASSWORD') ?: null);
        if ($password !== null) {
            return (string) $password;
        }

        if (!$input->isInteractive()) {
            $this->error($output, 'Missing admin password. Pass --password=... or set THEATRECMS_ADMIN_PASSWORD.');
            return null;
        }

        $password = (string) $this->questionHelper()->ask($input, $output, $this->hidden('Admin password: '));
        $confirmation = (string) $this->questionHelper()->ask($input, $output, $this->hidden('Confirm password: '));
        if ($password !== $confirmation) {
            $this->error($output, 'Passwords do not match.');
            return null;
        }

        return $password;
    }

    private function hidden(string $prompt): Question
    {
        return (new Question($prompt))->setHidden(true)->setHiddenFallback(true);
    }

    private function questionHelper(): QuestionHelper
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        return $helper;
    }

    private function error(OutputInterface $output, string $message): void
    {
        $errorOutput = $output instanceof \Symfony\Component\Console\Output\ConsoleOutputInterface
            ? $output->getErrorOutput()
            : $output;
        $errorOutput->writeln("<error>{$message}</error>");
    }
}
