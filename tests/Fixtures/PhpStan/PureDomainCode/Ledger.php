<?php

declare(strict_types=1);

namespace App\Alpha\Domain\Model;

use App\Alpha\Facade\AlphaFacadeInterface;
use App\Alpha\Facade\Dto\ThingDto;
use App\Common\Domain\Service\DateAndTimeService;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV7;

final class Ledger
{
    /**
     * @return list<string>
     */
    public function record(string $entryId, string $at, ThingDto $thing): array
    {
        return [Uuid::fromString($entryId)->toRfc4122(), new UuidV7($entryId)->toRfc4122(), $at, $thing->isDone() ? 'done' : 'open'];
    }

    /**
     * @return list<string>
     */
    public function recordNow(AlphaFacadeInterface $things): array
    {
        return [
            DateAndTimeService::getDateTimeImmutable()->format('c'),
            (string) time(),
            Uuid::v7()->toRfc4122(),
            (string) random_int(1, 6),
            new UuidV4()->toRfc4122(),
            (string) $things->thing('thing')->doneOn,
        ];
    }
}
