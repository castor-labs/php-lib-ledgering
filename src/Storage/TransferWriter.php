<?php

declare(strict_types=1);

namespace Castor\Ledgering\Storage;

use Castor\Ledgering\Transfer;

/**
 * Writes transfers to storage.
 *
 * @internal You should not use this interface directly.
 */
interface TransferWriter
{
	/**
	 * Write a transfer to storage.
	 *
	 * @param Transfer $transfer The transfer to write
	 *
	 * @throws UnexpectedError if write operation fails
	 */
	public function write(Transfer $transfer): void;
}
