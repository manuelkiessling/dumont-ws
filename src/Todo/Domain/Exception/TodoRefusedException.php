<?php

declare(strict_types=1);

namespace App\Todo\Domain\Exception;

use RuntimeException;

/**
 * A business rule of the todo list refused what someone asked. Its message is meant for the person in front of the
 * page.
 */
abstract class TodoRefusedException extends RuntimeException
{
}
