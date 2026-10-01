<?php

namespace TheatreCMS\Tests\Unit\Console;

use Delight\Auth\DuplicateUsernameException;
use Delight\Auth\UserAlreadyExistsException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TheatreCMS\Console\Command\UserCreateAdminCommand;
use TheatreCMS\Repositories\UserRepository;

class UserCreateAdminCommandTest extends TestCase
{
    private const ENV = ['THEATRECMS_ADMIN_EMAIL', 'THEATRECMS_ADMIN_USERNAME', 'THEATRECMS_ADMIN_PASSWORD'];

    private UserRepository&MockObject $users;

    protected function setUp(): void
    {
        $this->users = $this->createMock(UserRepository::class);
        foreach (self::ENV as $name) {
            putenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach (self::ENV as $name) {
            putenv($name);
        }
    }

    public function testDoesNothingWhenAnAdminExists(): void
    {
        $this->users->method('hasAdminUser')->willReturn(true);
        $this->users->expects($this->never())->method('create');

        $tester = $this->tester();

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('already exists; nothing to do', $tester->getDisplay());
    }

    public function testCreatesAnAdminFromOptions(): void
    {
        $this->users->method('hasAdminUser')->willReturn(false);
        $this->users->expects($this->once())->method('create')->with([
            'email' => 'admin@example.com',
            'username' => 'admin',
            'password' => 'secret',
            'role' => 'admin',
        ])->willReturn(12);

        $tester = $this->tester();
        $status = $tester->execute(
            ['--email' => 'admin@example.com', '--username' => 'admin', '--password' => 'secret'],
            ['interactive' => false],
        );

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertSame("Created admin user #12 (admin@example.com).\n", $tester->getDisplay(true));
    }

    public function testForceCreatesAnotherAdmin(): void
    {
        $this->users->method('hasAdminUser')->willReturn(true);
        $this->users->expects($this->once())->method('create')->willReturn(2);

        $status = $this->tester()->execute(
            ['--force' => true, '--email' => 'b@example.com', '--username' => 'b', '--password' => 'pw'],
            ['interactive' => false],
        );

        $this->assertSame(Command::SUCCESS, $status);
    }

    public function testFallsBackToEnvironmentVariables(): void
    {
        putenv('THEATRECMS_ADMIN_EMAIL=env@example.com');
        putenv('THEATRECMS_ADMIN_USERNAME=envadmin');
        putenv('THEATRECMS_ADMIN_PASSWORD=envsecret');
        $this->users->method('hasAdminUser')->willReturn(false);
        $this->users->expects($this->once())->method('create')->with([
            'email' => 'env@example.com',
            'username' => 'envadmin',
            'password' => 'envsecret',
            'role' => 'admin',
        ])->willReturn(1);

        $this->assertSame(Command::SUCCESS, $this->tester()->execute([], ['interactive' => false]));
    }

    public function testPromptsForMissingValues(): void
    {
        $this->users->method('hasAdminUser')->willReturn(false);
        $this->users->expects($this->once())->method('create')->with([
            'email' => 'asked@example.com',
            'username' => 'asked',
            'password' => 'pw',
            'role' => 'admin',
        ])->willReturn(3);

        $tester = $this->tester();
        $tester->setInputs(['asked@example.com', 'asked', 'pw', 'pw']);

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
    }

    public function testRejectsMismatchedPasswords(): void
    {
        $this->users->method('hasAdminUser')->willReturn(false);
        $this->users->expects($this->never())->method('create');

        $tester = $this->tester();
        $tester->setInputs(['pw', 'other']);

        $this->assertSame(Command::FAILURE, $tester->execute(['--email' => 'a@example.com', '--username' => 'a']));
        $this->assertStringContainsString('Passwords do not match.', $tester->getDisplay());
    }

    public function testFailsWithoutInputWhenNotInteractive(): void
    {
        $this->users->method('hasAdminUser')->willReturn(false);
        $this->users->expects($this->never())->method('create');

        $tester = $this->tester();

        $this->assertSame(Command::FAILURE, $tester->execute([], ['interactive' => false]));
        $this->assertStringContainsString('Missing admin email', $tester->getDisplay());
    }

    public function testRejectsAnInvalidEmail(): void
    {
        $this->users->method('hasAdminUser')->willReturn(false);
        $this->users->expects($this->never())->method('create');

        $tester = $this->tester();

        $this->assertSame(Command::FAILURE, $tester->execute(['--email' => 'not-an-email'], ['interactive' => false]));
        $this->assertStringContainsString('Invalid email address: not-an-email', $tester->getDisplay());
    }

    /**
     * @return array<string, array{0: \Throwable, 1: string}>
     */
    public static function creationErrors(): array
    {
        return [
            'duplicate email' => [new UserAlreadyExistsException(), 'A user with that email already exists.'],
            'duplicate username' => [new DuplicateUsernameException(), 'That username is already taken.'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('creationErrors')]
    public function testReportsCreationErrors(\Throwable $error, string $message): void
    {
        $this->users->method('hasAdminUser')->willReturn(false);
        $this->users->expects($this->once())->method('create')->willThrowException($error);

        $tester = $this->tester();
        $status = $tester->execute(
            ['--email' => 'a@example.com', '--username' => 'a', '--password' => 'pw'],
            ['interactive' => false],
        );

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString($message, $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        // The command needs an application for its question helper.
        $application = new Application();
        $application->addCommand(new UserCreateAdminCommand($this->users));

        return new CommandTester($application->find('user:create-admin'));
    }
}
