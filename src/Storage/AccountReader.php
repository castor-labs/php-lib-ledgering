<?php

declare(strict_types=1);

namespace Castor\Ledgering\Storage;

use Castor\Ledgering\Account;
use Castor\Ledgering\Identifier;

/**
 * Represents a collection of accounts that can be read and transformed.
 *
 * @extends Reader<Account>
 */
interface AccountReader extends Reader
{
	/**
	 * Filter accounts by their IDs.
	 *
	 * @param Identifier ...$ids One or more account IDs to filter by
	 */
	public function ofId(Identifier ...$ids): self;

	/**
	 * Filter accounts by their primary external IDs.
	 *
	 * @param Identifier ...$ids One or more primary external IDs to filter by
	 */
	public function ofExternalIdPrimary(Identifier ...$ids): self;

	/**
	 * Filter accounts by their secondary external IDs.
	 *
	 * @param Identifier ...$ids One or more secondary external IDs to filter by
	 */
	public function ofExternalIdSecondary(Identifier ...$ids): self;
}
