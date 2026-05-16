<?php

declare(strict_types=1);

namespace Castor\Ledgering\Storage;

use Castor\Ledgering\AccountBalance;
use Castor\Ledgering\Identifier;

/**
 * Represents a collection of account balances that can be read and transformed.
 *
 * @extends Reader<AccountBalance>
 */
interface AccountBalanceReader extends Reader
{
	/**
	 * Filter account balances by their account IDs.
	 *
	 * @param Identifier ...$ids One or more account IDs to filter by
	 */
	public function ofAccountId(Identifier ...$ids): self;
}
