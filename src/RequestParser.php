<?php declare(strict_types=1);

namespace Laragraph\Utils;

use GraphQL\Server\Helper;
use GraphQL\Utils\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Safe\Exceptions\JsonException;

/**
 * Follows https://github.com/graphql/graphql-over-http/blob/main/spec/GraphQLOverHTTP.md.
 */
class RequestParser
{
    /**
     * @var \GraphQL\Server\Helper
     */
    protected $helper;

    public function __construct()
    {
        $this->helper = new Helper();
    }

    /**
     * Converts an incoming HTTP request to one or more OperationParams.
     *
     * @throws \Laragraph\Utils\BadRequestGraphQLException
     *
     * @return \GraphQL\Server\OperationParams|array<int, \GraphQL\Server\OperationParams>
     */
    public function parseRequest(Request $request)
    {
        $method = $request->getMethod();
        $bodyParams = [];
        /** @var array<string, mixed> $queryParams */
        $queryParams = $request->query();

        if ('POST' === $method) {
            /**
             * Never null, since Symfony defaults to application/x-www-form-urlencoded.
             *
             * @var string $contentType
             */
            $contentType = $request->header('Content-Type');

            if (Str::startsWith($contentType, ['application/json', 'application/graphql+json'])) {
                /** @var string $content */
                $content = $request->getContent();
                try {
                    $bodyParams = \Safe\json_decode($content, true);
                } catch (JsonException $e) {
                    throw new BadRequestGraphQLException("Invalid JSON: {$e->getMessage()}");
                }

                if (! is_array($bodyParams)) {
                    throw new BadRequestGraphQLException(
                        'GraphQL Server expects JSON object or array, but got '
                        . Utils::printSafeJson($bodyParams)
                    );
                }
            } elseif (Str::startsWith($contentType, 'application/graphql')) {
                /** @var string $content */
                $content = $request->getContent();
                $bodyParams = ['query' => $content];
            } elseif (Str::startsWith($contentType, 'application/x-www-form-urlencoded')) {
                /** @var array<string, mixed> $bodyParams */
                $bodyParams = $request->post();
            } elseif (Str::startsWith($contentType, 'multipart/form-data')) {
                $bodyParams = $this->inlineFiles($request);
            } else {
                throw new BadRequestGraphQLException('Unexpected content type: ' . Utils::printSafeJson($contentType));
            }
        }

        return $this->helper->parseRequestParams($method, $bodyParams, $queryParams);
    }

    /**
     * Inline file uploads given through a multipart request.
     *
     * @return array<mixed>
     */
    protected function inlineFiles(Request $request): array
    {
        /** @var string|null $mapParam */
        $mapParam = $request->post('map');
        if (null === $mapParam) {
            throw new BadRequestGraphQLException(
                'Could not find a valid map, be sure to conform to GraphQL multipart request specification: https://github.com/jaydenseric/graphql-multipart-request-spec'
            );
        }

        /** @var string|null $operationsParam */
        $operationsParam = $request->post('operations');
        if (null === $operationsParam) {
            throw new BadRequestGraphQLException(
                'Could not find valid operations, be sure to conform to GraphQL multipart request specification: https://github.com/jaydenseric/graphql-multipart-request-spec'
            );
        }

        /** @var array<string, mixed>|array<int, array<string, mixed>> $operations */
        $operations = \Safe\json_decode($operationsParam, true);

        /** @var array<int|string, array<int, string>> $map */
        $map = \Safe\json_decode($mapParam, true);

        foreach ($map as $fileKey => $operationsPaths) {
            /** @var array<string> $operationsPaths */
            $file = $request->file((string) $fileKey);

            foreach ($operationsPaths as $operationsPath) {
                Arr::set($operations, $operationsPath, $file);
            }
        }

        return $operations;
    }
}
