<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Exception;

use RuntimeException;

/**
 * A business rule of time tracking refused what someone asked. Its message is meant for the person in front of the
 * page.
 */
abstract class TimeTrackingRefusedException extends RuntimeException
{
}
