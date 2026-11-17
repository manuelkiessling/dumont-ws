<?php

declare(strict_types=1);

namespace App\Alpha\Facade;

use App\Alpha\Domain\Model\Thing;
use App\Alpha\Domain\Service\ThingService;

final class AlphaFacade
{
    public Thing $exposed;

    private Thing $kept;

    public function __construct(private ThingService $service)
    {
        $this->exposed = $service->thing();
        $this->kept    = $service->thing();
    }

    public function keptId(): string
    {
        return $this->convert($this->kept);
    }

    private function convert(Thing $thing): string
    {
        return $thing::class . $this->service::class;
    }
}
