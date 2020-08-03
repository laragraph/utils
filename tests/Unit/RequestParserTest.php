<?php

declare(strict_types=1);

namespace Laragraph\LaravelGraphQLUtils\Tests\Unit;

use Illuminate\Http\Request;
use Laragraph\LaravelGraphQLUtils\RequestParser;
use Orchestra\Testbench\TestCase;
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

    public function testNonSensicalContentTypeDefaultsToJson(): void
    {
        $query = /** @lang GraphQL */ '{ foo }';
        $request = $this->makeRequest(
            'POST',
            [],
            [],
            ['Content-Type' => 'foobar'],
            \Safe\json_encode(['query' => $query])
        );

        $parser = new RequestParser();
        /** @var \GraphQL\Server\OperationParams $params */
        $params = $parser->parseRequest($request);

        self::assertSame($query, $params->query);
    }

    public function testNoQuery(): void
    {
        $request = $this->makeRequest('GET');

        $parser = new RequestParser();
        /** @var \GraphQL\Server\OperationParams $params */
        $params = $parser->parseRequest($request);

        self::assertSame(null, $params->query);
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
