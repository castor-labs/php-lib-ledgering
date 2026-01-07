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

use Castor\Ledgering\Time\Instant;

/**
 * Represents a balance snapshot for an account at a specific point in time.
 *
 * Account balances are only recorded for accounts with the HISTORY flag set.
 * Each snapshot captures the account's balance after a transfer was executed.
 */
final readonly class AccountBalance
{
	public function __construct(
		public Identifier $accountId,
		public Balance $balance,
		public Instant $timestamp,
	) {}
}
