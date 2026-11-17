<?php

declare(strict_types=1);

namespace App\Alpha\Domain\Model;

use App\Common\Domain\Service\DateAndTimeService;
use DateTimeImmutable;

final readonly class EntryRecorded
{
    public DateTimeImmutable $recordedAt;

    public function __construct(
        public string $entryId
    ) {
        $this->recordedAt = DateAndTimeService::getDateTimeImmutable();
    }
}
