<?php

declare(strict_types=1);

namespace App\Alpha\Domain\Service;

use App\Common\Domain\Service\DateAndTimeService;
use Symfony\Component\Uid\Uuid;

final class ThingService implements ThingServiceInterface
{
    public function count(): int
    {
        return mb_strlen(Uuid::v7()->toRfc4122() . DateAndTimeService::getDateTimeImmutable()->format('c')) + time();
    }
}
