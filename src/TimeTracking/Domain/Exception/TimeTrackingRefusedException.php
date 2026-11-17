<?php

declare(strict_types=1);

namespace App\TimeTracking\Domain\Exception;

use RuntimeException;

/**
 * A rule of time tracking refused what someone asked. Its message is meant for the person in front of the screen.
 */
abstract class TimeTrackingRefusedException extends RuntimeException
{
}
