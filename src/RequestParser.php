<?php declare(strict_types=1);

namespace Laragraph\Utils;

use GraphQL\Server\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Safe\Exceptions\JsonException;

use function Safe\json_decode;

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
     * @throws \GraphQL\Server\RequestError
     * @throws \Laragraph\Utils\BadRequestGraphQLException
     *
     * @return \GraphQL\Server\OperationParams|array<int, \GraphQL\Server\OperationParams>
     */
    public function parseRequest(Request $request)
    {
        $method = $request->getMethod();
        $bodyParams = 'POST' === $method
            ? $this->bodyParams($request)
            : [];
        /** @var array<string, mixed> $queryParams Laravel type is not precise enough */
        $queryParams = $request->query();

        return $this->helper->parseRequestParams($method, $bodyParams, $queryParams);
    }

    /**
     * Extracts the body parameters from the request.
     *
     * @return array<mixed>
     */
    protected function bodyParams(Request $request): array
    {
        $contentType = $request->header('Content-Type');
        assert(is_string($contentType), 'Never null, since Symfony defaults to application/x-www-form-urlencoded.');

        if (Str::startsWith($contentType, 'multipart/form-data')) {
            return $this->inlineFiles($request);
        }

        if (Str::startsWith($contentType, 'application/graphql') && ! $request->isJson()) {
            return ['query' => $request->getContent()];
        }

        $bodyParams = $request->input();

        if (is_array($bodyParams) && count($bodyParams) > 0) {
            if (Arr::isAssoc($bodyParams)) {
                return $bodyParams;
            }

            $allAssoc = true;
            foreach ($bodyParams as $bodyParam) {
                if (! is_array($bodyParam) || ! Arr::isAssoc($bodyParam)) {
                    $allAssoc = false;
                }
            }
            if ($allAssoc) {
                return $bodyParams;
            }
        }

        if ($request->isJson()) {
            throw new BadRequestGraphQLException("GraphQL Server expects JSON object or array, but got: {$request->getContent()}.");
        }

        throw new BadRequestGraphQLException("Could not decode request with content type: \"{$contentType}\".");
    }

    /**
     * Inline file uploads given through a multipart request.
     *
     * Follows https://github.com/jaydenseric/graphql-multipart-request-spec.
     *
     * @throws BadMultipartRequestGraphQLException
     *
     * @return array<mixed>
     */
    protected function inlineFiles(Request $request): array
    {
        /** @var string|null $mapParam */
        $mapParam = $request->post('map');
        if (null === $mapParam) {
            throw new BadMultipartRequestGraphQLException('Could not find a valid map.');
        }

        /** @var string|null $operationsParam */
        $operationsParam = $request->post('operations');
        if (null === $operationsParam) {
            throw new BadMultipartRequestGraphQLException('Could not find a valid operations.');
        }

        try {
            /** Should be array<string, mixed>|array<int, array<string, mixed>>, but it's user input so can be anything */
            $operations = json_decode($operationsParam, true);
        } catch (JsonException $e) {
            throw new BadMultipartRequestGraphQLException('Parameter operations is not a valid json.', $e);
        }

        if (! is_array($operations)) {
            $type = gettype($operations);
            throw new BadMultipartRequestGraphQLException("Expected operations to be array, got: {$type}.");
        }

        try {
            /** Should be array<int|string, array<int, string>>, but it's user input so can be anything */
            $map = json_decode($mapParam, true);
        } catch (JsonException $e) {
            throw new BadMultipartRequestGraphQLException('Parameter map is not a valid json.', $e);
        }

        if (! is_array($map)) {
            $type = gettype($map);
            throw new BadMultipartRequestGraphQLException("Expected map to be array, got: {$type}.");
        }

        foreach ($map as $fileKey => $operationsPaths) {
            $file = $request->file((string) $fileKey);

            if (! is_iterable($operationsPaths)) {
                $type = gettype($operationsPaths);
                throw new BadMultipartRequestGraphQLException("Expected map to be array of arrays, got: {$type}");
            }

            foreach ($operationsPaths as $operationsPath) {
                if (! is_string($operationsPath)) {
                    $type = gettype($operationsPath);
                    throw new BadMultipartRequestGraphQLException("Expected map to be array of arrays of strings, got {$type}");
                }
                Arr::set($operations, $operationsPath, $file);
            }
        }

        return $operations;
    }
}
