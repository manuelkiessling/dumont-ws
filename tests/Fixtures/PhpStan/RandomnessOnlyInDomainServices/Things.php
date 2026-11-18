<?php

declare(strict_types=1);

namespace App\Alpha\Domain\Service {
    use Symfony\Component\Uid\Uuid;

    final class ThingsDomainService
    {
        public function newId(): string
        {
            return Uuid::v7()->toRfc4122();
        }
    }
}

namespace App\Alpha\Infrastructure\Storage {
    use Symfony\Component\Uid\Uuid;

    final class ThingStore
    {
        public function newId(): string
        {
            return Uuid::v7()->toRfc4122();
        }

        public function tempName(): string
        {
            return 'things.' . random_int(1, 1000) . '.tmp';
        }
    }
}

namespace App\Alpha\Presentation\Tui {
    use Symfony\Component\Uid\UuidV4;

    final class ThingScreen
    {
        public function key(): string
        {
            return new UuidV4()->toRfc4122();
        }
    }
}

namespace App\Tests\Unit\Alpha {
    use Symfony\Component\Uid\Uuid;

    final class ThingsTest
    {
        public function anId(): string
        {
            return Uuid::v7()->toRfc4122();
        }
    }
}
