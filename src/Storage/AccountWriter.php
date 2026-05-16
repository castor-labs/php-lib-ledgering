<?php

declare(strict_types=1);

namespace Castor\Ledgering\Storage;

use Castor\Ledgering\Account;

/**
 * Writes accounts to storage.
 *
 * @internal You should not use this interface directly.
 */
interface AccountWriter
{
	/**
	 * Write an account to storage.
	 *
	 * @param Account $account The account to write
	 *
	 * @throws UnexpectedError if write operation fails
	 */
	public function write(Account $account): void;
}
