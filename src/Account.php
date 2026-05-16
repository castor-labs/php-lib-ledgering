<?php

declare(strict_types=1);

namespace Castor\Ledgering;

use Castor\Ledgering\Time\Instant;

/**
 * Represents an account in the ledger.
 *
 * Accounts hold balances and track financial activity.
 */
final readonly class Account
{
	public function __construct(
		public Identifier $id,
		public Code $ledger,
		public Code $code,
		public AccountFlags $flags,
		public Identifier $externalIdPrimary,
		public Identifier $externalIdSecondary,
		public Code $externalCodePrimary,
		public Balance $balance,
		public Instant $timestamp,
	) {}
}
