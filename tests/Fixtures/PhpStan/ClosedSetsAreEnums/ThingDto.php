<?php

declare(strict_types=1);

namespace App\Alpha\Facade\Dto;

use App\Alpha\Facade\Enum\ThingKind;

final readonly class ThingDto
{
    public function __construct(
        public string    $id,
        public ?string   $subjectKind,
        public ThingKind $kind,
        public string    $contentType,
        public string    $kindness,
        public string    $status,
        public ?string   $sendingState,
        public ThingKind $state,
        public string    $statement
    ) {
    }
}
