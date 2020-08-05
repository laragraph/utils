<?php

declare(strict_types=1);

namespace Laragraph\LaravelGraphQLUtils;

use GraphQL\Error\InvariantViolation;
use GraphQL\Server\Helper;
use GraphQL\Server\OperationParams;
use GraphQL\Server\RequestError;
use GraphQL\Utils\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

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
     * @return OperationParams|array<int, OperationParams>
     *
     * @throws RequestError
     */
    public function parseRequest(Request $request)
    {
        $method = $request->getMethod();
        $bodyParams = [];
        $queryParams = $request->query();

        if ($method === 'POST') {
            $contentType = $request->header('Content-Type');

            if ($contentType === null) {
                throw new RequestError('Missing "Content-Type" header');
            }

            if (stripos($contentType, 'application/json') !== false) {
                $bodyParams = \Safe\json_decode($request->getContent(), true);

                if (! is_array($bodyParams)) {
                    throw new RequestError(
                        'GraphQL Server expects JSON object or array, but got '.
                        Utils::printSafeJson($bodyParams)
                    );
                }
            } else if (stripos($contentType, 'application/graphql') !== false) {
                $bodyParams = ['query' => $request->getContent()];
            } elseif (stripos($contentType, 'application/x-www-form-urlencoded') !== false) {
                $bodyParams = $request->post();
            } elseif (stripos($contentType, 'multipart/form-data') !== false) {
                $bodyParams = $this->inlineFiles($request);
            } else {
                throw new RequestError('Unexpected content type: '.Utils::printSafeJson($contentType));
            }
        }

        return $this->helper->parseRequestParams($method, $bodyParams, $queryParams);
    }

    /**
     * Inline file uploads given through a multipart request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<mixed>
     */
    protected function inlineFiles(Request $request): array
    {
        $mapParam = $request->post('map');
        if ($mapParam === null) {
            throw new InvariantViolation(
                'Could not find a valid map, be sure to conform to GraphQL multipart request specification: https://github.com/jaydenseric/graphql-multipart-request-spec'
            );
        }

        /** @var array<string, mixed>|array<int, array<string, mixed>> $operations */
        $operations = \Safe\json_decode($request->post('operations'), true);

        /** @var array<string, array<int, string>> $map */
        $map = \Safe\json_decode($mapParam, true);

        foreach ($map as $fileKey => $operationsPaths) {
            /** @var string $fileKey */
            /** @var array<string> $operationsPaths */
            $file = $request->file($fileKey);

            /** @var string $operationsPath */
            foreach ($operationsPaths as $operationsPath) {
                Arr::set($operations, $operationsPath, $file);
            }
        }

        return $operations;
    }
}
