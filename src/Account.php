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
