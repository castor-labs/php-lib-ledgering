<?php

declare(strict_types=1);

namespace Castor\Ledgering;

/**
 * Represents a balance with posted and pending amounts.
 *
 * Balances track both debits and credits, with separate tracking for
 * posted (committed) and pending (reserved) amounts.
 */
final readonly class Balance
{
	public function __construct(
		public Amount $debitsPosted,
		public Amount $creditsPosted,
		public Amount $debitsPending,
		public Amount $creditsPending,
	) {}

	/**
	 * Creates a zero balance.
	 */
	public static function zero(): self
	{
		$zero = Amount::zero();

		return new self($zero, $zero, $zero, $zero);
	}
}
