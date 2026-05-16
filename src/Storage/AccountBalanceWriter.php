<?php

declare(strict_types=1);

namespace Castor\Ledgering\Storage;

use Castor\Ledgering\AccountBalance;

/**
 * Writes account balances to storage.
 *
 * @internal You should not use this interface directly.
 */
interface AccountBalanceWriter
{
	/**
	 * Write an account balance to storage.
	 *
	 * @param AccountBalance $balance The account balance to write
	 *
	 * @throws UnexpectedError if write operation fails
	 */
	public function write(AccountBalance $balance): void;
}
