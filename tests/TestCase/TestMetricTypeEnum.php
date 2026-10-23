<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Test\TestCase;

/**
 * Backed enum for Rhythm metric type tests.
 */
enum TestMetricTypeEnum: string
{
    case Request = 'request';
    case User = 'user';
}
