<?php

declare(strict_types=1);

/**
 * @project Castor Ledgering
 * @link https://github.com/castor-labs/php-lib-ledgering
 * @package castor/ledgering
 * @author Matias Navarro-Carter mnavarrocarter@gmail.com
 * @license MIT
 * @copyright 2024-2025 CastorLabs Ltd
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Castor\Ledgering;

use Castor\Ledgering\Time\Instant;

/**
 * Command to expire (void) all pending transfers that have exceeded their timeout.
 *
 * This command finds all pending transfers where:
 * - The transfer has the PENDING flag
 * - The transfer has a non-zero timeout
 * - The current time exceeds (transfer.timestamp + transfer.timeout)
 *
 * For each expired transfer, a VOID_PENDING transfer is created to release
 * the reserved funds.
 */
final readonly class ExpirePendingTransfers
{
	private function __construct(
		public Instant $asOf,
	) {}

	/**
	 * Create a command to expire transfers as of the given instant.
	 *
	 * @param Instant $asOf The instant to use for expiration checks
	 */
	public static function asOf(Instant $asOf): self
	{
		return new self($asOf);
	}
}
