<?php

declare(strict_types=1);

namespace App\Todo\Domain\Exception;

use RuntimeException;

abstract class TodoRefusedException extends RuntimeException
{
}
