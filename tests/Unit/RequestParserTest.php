<?php

declare(strict_types=1);

namespace Laragraph\Utils\Tests\Unit;

use GraphQL\Server\RequestError;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Laragraph\Utils\RequestParser;
use Orchestra\Testbench\TestCase;
use Safe\Exceptions\JsonException;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class RequestParserTest extends TestCase
{
    public function testGetWithQuery(): void
    {
        $query = /** @lang GraphQL */ '{ foo }';
        $request = $this->makeRequest('GET', ['query' => $query]);

        $parser = new RequestParser();
        /** @var \GraphQL\Server\OperationParams $params */
        $params = $parser->parseRequest($request);

        self::assertSame($query, $params->query);
    }

    public function testPostWithJson(): void
    {
        $query = /** @lang GraphQL */ '{ foo }';
        $request = $this->makeRequest(
            'POST',
            [],
            [],
            ['Content-Type' => 'application/json'],
            \Safe\json_encode(['query' => $query])
        );

        $parser = new RequestParser();
        /** @var \GraphQL\Server\OperationParams $params */
        $params = $parser->parseRequest($request);

        self::assertSame($query, $params->query);
    }

    public function testPostWithQueryApplicationGraphQL(): void
    {
        $query = /** @lang GraphQL */ '{ foo }';
        $request = $this->makeRequest(
            'POST',
            [],
            [],
            ['Content-Type' => 'application/graphql'],
            $query
        );

        $parser = new RequestParser();
        /** @var \GraphQL\Server\OperationParams $params */
        $params = $parser->parseRequest($request);

        self::assertSame($query, $params->query);
    }

    public function testPostWithRegularForm(): void
    {
        $query = /** @lang GraphQL */ '{ foo }';
        $request = $this->makeRequest(
            'POST',
            ['query' => $query],
            [],
            ['Content-Type' => 'application/x-www-form-urlencoded']
        );

        $parser = new RequestParser();
        /** @var \GraphQL\Server\OperationParams $params */
        $params = $parser->parseRequest($request);

        self::assertSame($query, $params->query);
    }

    public function testPostDefaultsToRegularForm(): void
    {
        $query = /** @lang GraphQL */ '{ foo }';
        $request = $this->makeRequest(
            'POST',
            ['query' => $query]
        );

        $parser = new RequestParser();
        /** @var \GraphQL\Server\OperationParams $params */
        $params = $parser->parseRequest($request);

        self::assertSame($query, $params->query);
    }

    public function testNonSensicalContentType(): void
    {
        $request = $this->makeRequest(
            'POST',
            [],
            [],
            ['Content-Type' => 'foobar']
        );

        $parser = new RequestParser();
        $this->expectException(RequestError::class);
        $parser->parseRequest($request);
    }

    public function testNoQuery(): void
    {
        $request = $this->makeRequest('GET');

        $parser = new RequestParser();
        /** @var \GraphQL\Server\OperationParams $params */
        $params = $parser->parseRequest($request);

        self::assertSame(null, $params->query);
    }

    public function testInvalidJson(): void
    {
        $request = $this->makeRequest(
            'POST',
            [],
            [],
            ['Content-Type' => 'application/json'],
            'this is not valid json'
        );

        $parser = new RequestParser();
        $this->expectException(JsonException::class);
        $parser->parseRequest($request);
    }

    public function testNonArrayJson(): void
    {
        $request = $this->makeRequest(
            'POST',
            [],
            [],
            ['Content-Type' => 'application/json'],
            '"this should be a map with query, variables, etc."'
        );

        $parser = new RequestParser();
        $this->expectException(RequestError::class);
        $parser->parseRequest($request);
    }

    public function testMultipartFormRequest(): void
    {
        $file = UploadedFile::fake()->create('image.jpg', 500);

        $request = $this->makeRequest(
            'POST',
            [
                'operations' => /** @lang JSON */ '
                    {
                        "query": "mutation Upload($file: Upload!) { upload(file: $file) }",
                        "variables": {
                            "file": null
                        }
                    }
                ',
                'map' => /** @lang JSON */ '
                    {
                        "0": ["variables.file"]
                    }
                ',
            ],
            [
                '0' => $file,
            ],
            [
                'Content-Type' => 'multipart/form-data',
            ]
        );

        $parser = new RequestParser();
        /** @var \GraphQL\Server\OperationParams $params */
        $params = $parser->parseRequest($request);

        self::assertSame('mutation Upload($file: Upload!) { upload(file: $file) }', $params->query);

        $variables = $params->variables;
        self::assertNotNull($variables);
        /** @var array<string, mixed> $variables */
        self::assertSame($file, $variables['file']);
    }

    public function testMultipartFormWithoutMap(): void
    {
        $request = $this->makeRequest(
            'POST',
            [],
            [],
            [
                'Content-Type' => 'multipart/form-data',
            ]
        );

        $parser = new RequestParser();
        $this->expectException(RequestError::class);
        $parser->parseRequest($request);
    }

    public function testMultipartFormWithoutOperations(): void
    {
        $request = $this->makeRequest(
            'POST',
            [
                'map' => /** @lang JSON */ '
                    {
                        "0": ["variables.file"]
                    }
                ',
            ],
            [],
            [
                'Content-Type' => 'multipart/form-data',
            ]
        );

        $parser = new RequestParser();
        $this->expectException(RequestError::class);
        $parser->parseRequest($request);
    }

    /**
     * @param  string  $method
     * @param  array<mixed>  $parameters
     * @param  array<mixed>  $files
     * @param  array<mixed>  $headers
     * @param  string|resource|null  $content
     * @return \Illuminate\Http\Request
     */
    public function makeRequest(string $method, array $parameters = [], array $files = [], array $headers = [], $content = null): Request
    {
        $symfonyRequest = SymfonyRequest::create(
            'http://foo.bar/graphql',
            $method,
            $parameters,
            [],
            $files,
            $this->transformHeadersToServerVars($headers),
            $content
        );

        return Request::createFromBase($symfonyRequest);
    }
}
