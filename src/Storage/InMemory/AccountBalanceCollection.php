<?php

declare(strict_types=1);

namespace Castor\Ledgering\Storage\InMemory;

use Castor\Ledgering\AccountBalance;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\Storage\AccountBalanceReader;
use Castor\Ledgering\Storage\AccountBalanceWriter;

/**
 * In-memory collection of account balances.
 *
 * Read operations return immutable filtered views.
 * Write operations modify the items array.
 *
 * @extends Collection<AccountBalance>
 */
final class AccountBalanceCollection extends Collection implements AccountBalanceReader, AccountBalanceWriter
{
	#[\Override]
	public function ofAccountId(Identifier ...$ids): self
	{
		return $this->filter(static function (AccountBalance $balance) use ($ids): bool {
			foreach ($ids as $id) {
				if ($balance->accountId->equals($id)) {
					return true;
				}
			}

			return false;
		});
	}

	#[\Override]
	public function write(AccountBalance $balance): void
	{
		// Account balances are append-only (historical records)
		$this->items[] = $balance;
	}
}
