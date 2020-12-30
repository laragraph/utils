<?php

declare(strict_types=1);

namespace Laragraph\Utils;

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
     * @return \GraphQL\Server\OperationParams|array<int, \GraphQL\Server\OperationParams>
     *
     * @throws \GraphQL\Server\RequestError
     */
    public function parseRequest(Request $request)
    {
        $method = $request->getMethod();
        $bodyParams = [];
        /** @var array<string, mixed> $queryParams */
        $queryParams = $request->query();

        if ($method === 'POST') {
            /**
             * Never null, since Symfony defaults to application/x-www-form-urlencoded.
             *
             * @var string $contentType
             */
            $contentType = $request->header('Content-Type');

            if (stripos($contentType, 'application/json') !== false) {
                /** @var string $content */
                $content = $request->getContent();
                $bodyParams = \Safe\json_decode($content, true);

                if (! is_array($bodyParams)) {
                    throw new RequestError(
                        'GraphQL Server expects JSON object or array, but got '.
                        Utils::printSafeJson($bodyParams)
                    );
                }
            } elseif (stripos($contentType, 'application/graphql') !== false) {
                /** @var string $content */
                $content = $request->getContent();
                $bodyParams = ['query' => $content];
            } elseif (stripos($contentType, 'application/x-www-form-urlencoded') !== false) {
                /** @var array<string, mixed> $bodyParams */
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
        /** @var string|null $mapParam */
        $mapParam = $request->post('map');
        if ($mapParam === null) {
            throw new RequestError(
                'Could not find a valid map, be sure to conform to GraphQL multipart request specification: https://github.com/jaydenseric/graphql-multipart-request-spec'
            );
        }

        /** @var string|null $operationsParam */
        $operationsParam = $request->post('operations');
        if ($operationsParam === null) {
            throw new RequestError(
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

            /** @var string $operationsPath */
            foreach ($operationsPaths as $operationsPath) {
                Arr::set($operations, $operationsPath, $file);
            }
        }

        return $operations;
    }
}
