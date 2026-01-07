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

use Castor\Ledgering\Time\Duration;
use Castor\Ledgering\Time\Instant;

/**
 * Represents a transfer between two accounts.
 *
 * Transfers are immutable once created.
 */
final readonly class Transfer
{
	public function __construct(
		public Identifier $id,
		public Identifier $debitAccountId,
		public Identifier $creditAccountId,
		public Amount $amount,
		public Identifier $pendingId,
		public Code $ledger,
		public Code $code,
		public TransferFlags $flags,
		public Duration $timeout,
		public Identifier $externalIdPrimary,
		public Identifier $externalIdSecondary,
		public Code $externalCodePrimary,
		public Instant $timestamp,
	) {}
}
