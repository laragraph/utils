<?php

declare(strict_types=1);

namespace Laragraph\LaravelGraphQLUtils;

use GraphQL\Error\InvariantViolation;
use GraphQL\Server\Helper;
use GraphQL\Server\OperationParams;
use GraphQL\Server\RequestError;
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
        if ($request->isMethod('GET')) {
            $bodyParams = [];
        } else {
            // Symfony defaults to 'application/x-www-form-urlencoded' for POST requests
            $contentType = $request->header('content-type');

            if ($contentType === 'application/graphql') {
                /** @var string $content */
                $content = $request->getContent();
                $bodyParams = ['query' => $content];
            } elseif ($contentType === 'multipart/form-data') {
                $bodyParams = $this->inlineFiles($request);
            } else {
                // In all other cases, we assume we are given JSON encoded input
                /** @var string $content */
                $content = $request->getContent();
                $bodyParams = \Safe\json_decode($content, true);
            }
        }

        return $this->helper->parseRequestParams(
            $request->getMethod(),
            $bodyParams,
            $request->all()
        );
    }

    /**
     * Inline file uploads given through a multipart request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<mixed>
     */
    protected function inlineFiles(Request $request): array
    {
        if (! $request->has('map')) {
            throw new InvariantViolation(
                'Could not find a valid map, be sure to conform to GraphQL multipart request specification: https://github.com/jaydenseric/graphql-multipart-request-spec'
            );
        }

        /** @var array<string, mixed>|array<int, array<string, mixed>> $operations */
        $operations = \Safe\json_decode($request->input('operations'), true);

        /** @var array<string, array<int, string>> $map */
        $map = \Safe\json_decode($request->input('map'), true);

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
