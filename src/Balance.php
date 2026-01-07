<?php

declare(strict_types=1);

/**
 * @project Castor Ledgering
 * @link https://github.com/castor-labs/php-lib-ledgering
 * @package castor/ledgering
 * @author Matias Navarro-Carter mnavarrocarter@gmail.com
 * @license MIT
 * @copyright 2024-2026 CastorLabs Ltd
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
