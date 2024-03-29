<?php declare(strict_types=1);

namespace Laragraph\Utils\Tests\Unit;

use GraphQL\Server\OperationParams;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Laragraph\Utils\BadMultipartRequestGraphQLException;
use Laragraph\Utils\BadRequestGraphQLException;
use Laragraph\Utils\RequestParser;
use Orchestra\Testbench\TestCase;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

final class RequestParserTest extends TestCase
{
    public function testGetWithQuery(): void
    {
        $query = /** @lang GraphQL */ '{ foo }';
        $request = $this->makeRequest('GET', ['query' => $query]);

        $params = (new RequestParser())->parseRequest($request);

        self::assertInstanceOf(OperationParams::class, $params);
        self::assertSame($query, $params->query);
    }

    /** @dataProvider jsonLikeContentTypes */
    public function testPostWithJsonLike(string $contentType): void
    {
        $query = /** @lang GraphQL */ '{ foo }';
        $request = $this->makeRequest(
            'POST',
            [],
            [],
            ['Content-Type' => $contentType],
            \Safe\json_encode(['query' => $query])
        );
        $params = (new RequestParser())->parseRequest($request);

        self::assertInstanceOf(OperationParams::class, $params);
        self::assertSame($query, $params->query);
    }

    /** @return iterable<array{string}> */
    public static function jsonLikeContentTypes(): iterable
    {
        yield ['application/json'];
        yield ['application/graphql+json'];
        yield ['application/json;charset=UTF-8'];
    }

    /** @dataProvider graphQLContentTypes */
    public function testPostWithQueryApplicationGraphQL(string $contentType): void
    {
        $query = /** @lang GraphQL */ '{ foo }';
        $request = $this->makeRequest(
            'POST',
            [],
            [],
            ['Content-Type' => $contentType],
            $query
        );
        $params = (new RequestParser())->parseRequest($request);

        self::assertInstanceOf(OperationParams::class, $params);
        self::assertSame($query, $params->query);
    }

    /** @return iterable<array{string}> */
    public static function graphQLContentTypes(): iterable
    {
        yield ['application/graphql'];
        yield ['application/graphql;charset=UTF-8'];
    }

    /** @dataProvider formContentTypes */
    public function testPostWithRegularForm(string $contentType): void
    {
        $query = /** @lang GraphQL */ '{ foo }';
        $contentType = 'application/x-www-form-urlencoded';
        $request = $this->makeRequest(
            'POST',
            ['query' => $query],
            [],
            ['Content-Type' => $contentType]
        );
        $params = (new RequestParser())->parseRequest($request);

        self::assertInstanceOf(OperationParams::class, $params);
        self::assertSame($query, $params->query);
    }

    /** @return iterable<array{string}> */
    public static function formContentTypes(): iterable
    {
        yield ['application/x-www-form-urlencoded'];
        yield ['application/x-www-form-urlencoded;bla;blub'];
    }

    public function testPostWithBatchedRequest(): void
    {
        $fooQuery = /** @lang GraphQL */ '{ foo }';
        $barQuery = /** @lang GraphQL */ '{ bar }';
        $request = $this->makeRequest(
            'POST',
            [],
            [],
            ['Content-Type' => 'application/json'],
            \Safe\json_encode([
                ['query' => $fooQuery],
                ['query' => $barQuery],
            ])
        );
        $params = (new RequestParser())->parseRequest($request);

        self::assertIsArray($params);
        [$fooParams, $barParams] = $params;
        self::assertSame($fooQuery, $fooParams->query);
        self::assertSame($barQuery, $barParams->query);
    }

    public function testPostDefaultsToRegularForm(): void
    {
        $query = /** @lang GraphQL */ '{ foo }';
        $request = $this->makeRequest(
            'POST',
            ['query' => $query]
        );

        $params = (new RequestParser())->parseRequest($request);
        self::assertInstanceOf(OperationParams::class, $params);

        self::assertSame($query, $params->query);
    }

    /** @dataProvider nonsensicalContentTypes */
    public function testNonsensicalContentTypes(string $contentType): void
    {
        $request = $this->makeRequest(
            'POST',
            [],
            [],
            ['Content-Type' => $contentType]
        );
        $parser = new RequestParser();

        $this->expectException(BadRequestGraphQLException::class);
        $this->expectExceptionMessage("Could not decode request with content type: \"{$contentType}\"");
        $parser->parseRequest($request);
    }

    /** @return iterable<array{string}> */
    public static function nonsensicalContentTypes(): iterable
    {
        yield ['foobar'];
        yield ['application/foobar'];
        yield ['application/josn'];
        yield ['application/grapql'];
    }

    public function testNoQuery(): void
    {
        $request = $this->makeRequest('GET');
        $params = (new RequestParser())->parseRequest($request);

        self::assertInstanceOf(OperationParams::class, $params);
        self::assertNull($params->query);
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

        $this->expectException(BadRequestGraphQLException::class);
        $this->expectExceptionMessage('GraphQL Server expects JSON object or array, but got: this is not valid json');

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

        $this->expectException(BadRequestGraphQLException::class);
        $this->expectExceptionMessage('GraphQL Server expects JSON object or array, but got: "this should be a map with query, variables, etc."');

        $parser->parseRequest($request);
    }

    /** @dataProvider multipartFormContentTypes */
    public function testMultipartFormRequest(string $contentType): void
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
                'Content-Type' => $contentType,
            ]
        );

        $params = (new RequestParser())->parseRequest($request);

        self::assertInstanceOf(OperationParams::class, $params);
        self::assertSame(/** @lang GraphQL */ 'mutation Upload($file: Upload!) { upload(file: $file) }', $params->query);

        $variables = $params->variables;
        self::assertNotNull($variables);
        /** @var array<string, mixed> $variables */
        self::assertSame($file, $variables['file']);
    }

    /** @return iterable<array{string}> */
    public static function multipartFormContentTypes(): iterable
    {
        yield ['multipart/form-data'];
        yield ['multipart/form-data; boundary=----WebkitFormBoundaryasodfh98ho1hfdsdfadfNX'];
    }

    public function testMultipartFormMapsAndOperationsDontMatch(): void
    {
        $file = UploadedFile::fake()->create('image.jpg', 500);

        $request = $this->makeRequest(
            'POST',
            [
                'operations' => /** @lang JSON */ '
                    {
                        "query": "mutation Upload($file: Upload!) { upload(file: $file) }",
                        "variables": {
                            "file": "test"
                        }
                    }
                ',
                'map' => /** @lang JSON */ '
                    {
                        "0": ["variables.file.0"]
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

        $params = (new RequestParser())->parseRequest($request);

        self::assertInstanceOf(OperationParams::class, $params);
        self::assertSame(/** @lang GraphQL */ 'mutation Upload($file: Upload!) { upload(file: $file) }', $params->query);

        $variables = $params->variables;
        self::assertNotNull($variables);
        /** @var array<string, array<mixed>> $variables */
        self::assertSame($file, $variables['file'][0]);
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

        $this->expectException(BadMultipartRequestGraphQLException::class);
        $parser->parseRequest($request);
    }

    public function testMultipartFormWithNonJsonOperations(): void
    {
        $request = $this->makeRequest(
            'POST',
            [
                'operations' => '1,2,3',
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

        $this->expectException(BadMultipartRequestGraphQLException::class);
        $parser->parseRequest($request);
    }

    public function testMultipartFormWithNonArrayOperations(): void
    {
        $request = $this->makeRequest(
            'POST',
            [
                'operations' => /** @lang JSON */ '
                    "query {id}"
                ',
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

        $this->expectException(BadMultipartRequestGraphQLException::class);
        $parser->parseRequest($request);
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

        $this->expectException(BadMultipartRequestGraphQLException::class);
        $parser->parseRequest($request);
    }

    public function testMultipartFormWithNonArrayMap(): void
    {
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
                    "test"
                ',
            ],
            [],
            [
                'Content-Type' => 'multipart/form-data',
            ]
        );

        $parser = new RequestParser();

        $this->expectException(BadMultipartRequestGraphQLException::class);
        $parser->parseRequest($request);
    }

    public function testMultipartFormWithWrongMapElements(): void
    {
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
                        "0": "variables.file"
                    }
                ',
            ],
            [],
            [
                'Content-Type' => 'multipart/form-data',
            ]
        );

        $parser = new RequestParser();

        $this->expectException(BadMultipartRequestGraphQLException::class);
        $parser->parseRequest($request);
    }

    public function testMultipartFormWithWrongMapSubElements(): void
    {
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
                'map' => '1,2,3',
            ],
            [],
            [
                'Content-Type' => 'multipart/form-data',
            ]
        );

        $parser = new RequestParser();

        $this->expectException(BadMultipartRequestGraphQLException::class);
        $parser->parseRequest($request);
    }

    public function testMultipartFormWithNonJsonMap(): void
    {
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
                        "0": [["variables.file"]]
                    }
                ',
            ],
            [],
            [
                'Content-Type' => 'multipart/form-data',
            ]
        );

        $parser = new RequestParser();

        $this->expectException(BadMultipartRequestGraphQLException::class);
        $parser->parseRequest($request);
    }

    /**
     * @param  array<mixed>  $parameters
     * @param  array<mixed>  $files
     * @param  array<mixed>  $headers
     * @param  string|resource|null  $content
     */
    private function makeRequest(string $method, array $parameters = [], array $files = [], array $headers = [], $content = null): Request
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
