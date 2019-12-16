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
        $query = '{ foo }';
        $request = $this->makeRequest('GET', ['query' => $query]);

        $parser = new RequestParser();
        /** @var \GraphQL\Server\OperationParams $params */
        $params = $parser->parseRequest($request);

        self::assertSame($query, $params->query);
    }

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
