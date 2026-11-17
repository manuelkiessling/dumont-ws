<?php

declare(strict_types=1);

namespace App\Alpha\Domain\Model;

use App\Alpha\Domain\Service\ThingServiceInterface;
use Random\Randomizer;

final class ThingPolicy
{
    /**
     * @param list<string> $thingIds
     */
    public static function mayRecord(string $day, array $thingIds): bool
    {
        return $day !== '' && $thingIds !== [];
    }

    /**
     * @param list<string> $thingIds
     */
    public static function mayRecordToday(array $thingIds, ThingServiceInterface $things): bool
    {
        shuffle($thingIds);

        return date('Y-m-d') !== '' && $things->count() > 0 && new Randomizer()->getInt(0, 1) === 1;
    }
}
