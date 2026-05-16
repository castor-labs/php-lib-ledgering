<?php

declare(strict_types=1);

namespace Castor\Ledgering\Time;

/**
 * Provides the current time.
 *
 * This abstraction allows for testable time-dependent code.
 */
interface Clock
{
	/**
	 * Get the current instant.
	 */
	public function now(): Instant;
}
