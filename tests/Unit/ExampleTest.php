<?php

declare(strict_types=1);

namespace Laragraph\LaravelGraphQLUtils\Tests\Unit;

use PHPUnit\Framework;
use Laragraph\LaravelGraphQLUtils\Example;

class ExampleTest extends Framework\TestCase
{
    public function testGreetIncludesName(): void
    {
        $name = 'laragraph';
        $example = new Example($name);

        self::assertStringContainsString($name, $example->greet());
    }
}
