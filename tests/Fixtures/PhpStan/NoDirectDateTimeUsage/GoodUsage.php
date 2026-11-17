<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\PhpStan\NoDirectDateTimeUsage;

// any lib class to ensure autoloading

use App\Common\Domain\Service\DateAndTimeService;

final class GoodUsage
{
    public function run(): void
    {
        $now = DateAndTimeService::getDateTimeImmutable();
    }
}
