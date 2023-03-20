<?php declare(strict_types=1);

namespace Laragraph\Utils;

use GraphQL\Server\Helper;
use GraphQL\Utils\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

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
     * Inline file uploads given through a multipart request.
     *
     * @return array<mixed>
     */
    protected function inlineFiles(Request $request): array
    {
        /** @var string|null $mapParam */
        $mapParam = $request->post('map');
        if (null === $mapParam) {
            throw new BadRequestGraphQLException('Could not find a valid map, be sure to conform to GraphQL multipart request specification: https://github.com/jaydenseric/graphql-multipart-request-spec');
        }

        /** @var string|null $operationsParam */
        $operationsParam = $request->post('operations');
        if (null === $operationsParam) {
            throw new BadRequestGraphQLException('Could not find valid operations, be sure to conform to GraphQL multipart request specification: https://github.com/jaydenseric/graphql-multipart-request-spec');
        }

        /** @var array<string, mixed>|array<int, array<string, mixed>> $operations */
        $operations = json_decode($operationsParam, true);

        /** @var array<int|string, array<int, string>> $map */
        $map = json_decode($mapParam, true);

        foreach ($map as $fileKey => $operationsPaths) {
            /** @var array<string> $operationsPaths */
            $file = $request->file((string) $fileKey);

            foreach ($operationsPaths as $operationsPath) {
                Arr::set($operations, $operationsPath, $file);
            }
        }

        return $operations;
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

        $bodyParams = $request->input();

        if (is_array($bodyParams) && Arr::isAssoc($bodyParams)) {
            return $bodyParams;
        }

        if (Str::startsWith($contentType, 'application/graphql')) {
            return ['query' => $request->getContent()];
        }

        if ($request->isJson()) {
            throw new BadRequestGraphQLException("GraphQL Server expects JSON object or array, but got: {$request->getContent()}.");
        }

        throw new BadRequestGraphQLException('Unexpected content type: ' . Utils::printSafeJson($contentType));
    }
}
