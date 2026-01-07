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

/**
 * Command to create a new transfer in the ledger.
 */
final readonly class CreateTransfer
{
	private function __construct(
		public Identifier $id,
		public Identifier $debitAccountId,
		public Identifier $creditAccountId,
		public Amount $amount,
		public Code $ledger,
		public Code $code,
		public TransferFlags $flags,
		public Identifier $pendingId,
		public Duration $timeout,
		public Identifier $externalIdPrimary,
		public Identifier $externalIdSecondary,
		public Code $externalCodePrimary,
	) {}

	public static function with(
		Identifier $id,
		Identifier $debitAccountId,
		Identifier $creditAccountId,
		Amount|int $amount,
		Code|int $ledger,
		Code|int $code,
		int $flags = 0,
		?Identifier $pendingId = null,
		?Duration $timeout = null,
		?Identifier $externalIdPrimary = null,
		?Identifier $externalIdSecondary = null,
		Code|int $externalCodePrimary = 1,
	): self {
		return new self(
			id: $id,
			debitAccountId: $debitAccountId,
			creditAccountId: $creditAccountId,
			amount: \is_int($amount) ? Amount::of($amount) : $amount,
			ledger: \is_int($ledger) ? Code::of($ledger) : $ledger,
			code: \is_int($code) ? Code::of($code) : $code,
			flags: TransferFlags::of($flags),
			pendingId: $pendingId ?? Identifier::zero(),
			timeout: $timeout ?? Duration::zero(),
			externalIdPrimary: $externalIdPrimary ?? Identifier::zero(),
			externalIdSecondary: $externalIdSecondary ?? Identifier::zero(),
			externalCodePrimary: \is_int($externalCodePrimary) ? Code::of($externalCodePrimary) : $externalCodePrimary,
		);
	}
}
