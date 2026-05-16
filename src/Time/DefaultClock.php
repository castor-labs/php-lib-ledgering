<?php

declare(strict_types=1);

namespace Castor\Ledgering\Time;

/**
 * Default clock implementation that returns the system time.
 */
final readonly class DefaultClock implements Clock
{
	#[\Override]
	public function now(): Instant
	{
		return Instant::now();
	}
}
