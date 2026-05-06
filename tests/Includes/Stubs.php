<?php

/**
 * Stub classes for external dependencies not available in the test environment.
 * These allow PHPUnit to create mocks of classes from packages that are not
 * installed in the vendor directory during development.
 */

// PSR HTTP interfaces
namespace Psr\Http\Message {
    interface StreamInterface
    {
        public function __toString(): string;
        public function close(): void;
        public function detach();
        public function getSize(): ?int;
        public function tell(): int;
        public function eof(): bool;
        public function isSeekable(): bool;
        public function seek(int $offset, int $whence = SEEK_SET): void;
        public function rewind(): void;
        public function isWritable(): bool;
        public function write(string $string): int;
        public function isReadable(): bool;
        public function read(int $length): string;
        public function getContents(): string;
        public function getMetadata(?string $key = null);
    }

    interface MessageInterface
    {
        public function getProtocolVersion(): string;
        public function withProtocolVersion(string $version): static;
        public function getHeaders(): array;
        public function hasHeader(string $name): bool;
        public function getHeader(string $name): array;
        public function getHeaderLine(string $name): string;
        public function withHeader(string $name, $value): static;
        public function withAddedHeader(string $name, $value): static;
        public function withoutHeader(string $name): static;
        public function getBody(): StreamInterface;
        public function withBody(StreamInterface $body): static;
    }

    interface RequestInterface extends MessageInterface
    {
        public function getRequestTarget(): string;
        public function withRequestTarget(string $requestTarget): static;
        public function getMethod(): string;
        public function withMethod(string $method): static;
        public function getUri(): UriInterface;
        public function withUri(UriInterface $uri, bool $preserveHost = false): static;
    }

    interface UriInterface
    {
        public function getScheme(): string;
        public function getAuthority(): string;
        public function getUserInfo(): string;
        public function getHost(): string;
        public function getPort(): ?int;
        public function getPath(): string;
        public function getQuery(): string;
        public function getFragment(): string;
        public function withScheme(string $scheme): static;
        public function withUserInfo(string $user, ?string $password = null): static;
        public function withHost(string $host): static;
        public function withPort(?int $port): static;
        public function withPath(string $path): static;
        public function withQuery(string $query): static;
        public function withFragment(string $fragment): static;
        public function __toString(): string;
    }

    interface ResponseInterface extends MessageInterface
    {
        public function getStatusCode(): int;
        public function withStatus(int $code, string $reasonPhrase = ''): static;
        public function getReasonPhrase(): string;
    }

    interface ServerRequestInterface extends RequestInterface
    {
        public function getServerParams(): array;
        public function getCookieParams(): array;
        public function withCookieParams(array $cookies): static;
        public function getQueryParams(): array;
        public function withQueryParams(array $query): static;
        public function getUploadedFiles(): array;
        public function withUploadedFiles(array $uploadedFiles): static;
        public function getParsedBody();
        public function withParsedBody($data): static;
        public function getAttributes(): array;
        public function getAttribute(string $name, $default = null);
        public function withAttribute(string $name, $value): static;
        public function withoutAttribute(string $name): static;
    }

    interface ResponseFactoryInterface
    {
        public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface;
    }
}

namespace Psr\Http\Server {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;

    interface RequestHandlerInterface
    {
        public function handle(ServerRequestInterface $request): ResponseInterface;
    }

    interface MiddlewareInterface
    {
        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface;
    }
}

// Doctrine interfaces
namespace Doctrine\ORM {
    interface EntityManagerInterface {}
}

// Slim stubs
namespace Slim\Views {
    class Twig
    {
        public function render($response, string $template, array $data = []) {}
        public function addExtension($extension): void {}
        public function getEnvironment(): \Twig\Environment { return new \Twig\Environment(); }
    }
}

namespace Twig {
    class Environment
    {
        public function addGlobal(string $name, $value): void {}
    }
}

namespace Twig\Error {
    class LoaderError extends \RuntimeException {}
    class RuntimeError extends \RuntimeException {}
    class SyntaxError extends \RuntimeException {}
}

namespace Slim\Psr7\Factory {
    use Psr\Http\Message\ResponseFactoryInterface;
    use Psr\Http\Message\ResponseInterface;

    class ResponseFactory implements ResponseFactoryInterface
    {
        public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
        {
            return new \Slim\Psr7\Response($code);
        }
    }
}

namespace Slim\Psr7 {
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\StreamInterface;

    class Response implements ResponseInterface
    {
        private int $status;
        private array $headers = [];

        public function __construct(int $status = 200)
        {
            $this->status = $status;
        }

        public function getStatusCode(): int { return $this->status; }
        public function withStatus(int $code, string $reasonPhrase = ''): static
        {
            $clone = clone $this;
            $clone->status = $code;
            return $clone;
        }
        public function getReasonPhrase(): string { return ''; }
        public function getProtocolVersion(): string { return '1.1'; }
        public function withProtocolVersion(string $version): static { return clone $this; }
        public function getHeaders(): array { return $this->headers; }
        public function hasHeader(string $name): bool { return isset($this->headers[strtolower($name)]); }
        public function getHeader(string $name): array { return $this->headers[strtolower($name)] ?? []; }
        public function getHeaderLine(string $name): string { return implode(', ', $this->getHeader($name)); }
        public function withHeader(string $name, $value): static
        {
            $clone = clone $this;
            $clone->headers[strtolower($name)] = (array) $value;
            return $clone;
        }
        public function withAddedHeader(string $name, $value): static { return $this->withHeader($name, $value); }
        public function withoutHeader(string $name): static { return clone $this; }
        public function getBody(): \Psr\Http\Message\StreamInterface { return new Stream(); }
        public function withBody(\Psr\Http\Message\StreamInterface $body): static { return clone $this; }
    }

    class Stream implements \Psr\Http\Message\StreamInterface
    {
        private string $content = '';
        public function __toString(): string { return $this->content; }
        public function close(): void {}
        public function detach() { return null; }
        public function getSize(): ?int { return strlen($this->content); }
        public function tell(): int { return 0; }
        public function eof(): bool { return true; }
        public function isSeekable(): bool { return false; }
        public function seek(int $offset, int $whence = SEEK_SET): void {}
        public function rewind(): void {}
        public function isWritable(): bool { return true; }
        public function write(string $string): int { $this->content .= $string; return strlen($string); }
        public function isReadable(): bool { return true; }
        public function read(int $length): string { return $this->content; }
        public function getContents(): string { return $this->content; }
        public function getMetadata(?string $key = null) { return null; }
    }
}

// Delight Auth stubs
namespace Delight\Auth {
    class Auth
    {
        public function __construct($db) {}
        public function isLoggedIn(): bool { return false; }
        public function login(string $email, string $password, ?int $rememberDuration = null): void {}
        public function register(string $email, string $password, ?string $username = null, ?callable $callback = null): int { return 0; }
    }

    class AuthError extends \RuntimeException {}
    class InvalidEmailException extends AuthError {}
    class InvalidPasswordException extends AuthError {}
    class EmailNotVerifiedException extends AuthError {}
    class TooManyRequestsException extends AuthError {}
    class SecondFactorRequiredException extends AuthError {}
    class UserAlreadyExistsException extends AuthError {}
}
