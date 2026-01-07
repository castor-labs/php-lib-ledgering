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
 * Command to create a new account in the ledger.
 */
final readonly class CreateAccount
{
	private function __construct(
		public Identifier $id,
		public Code $ledger,
		public Code $code,
		public AccountFlags $flags,
		public Identifier $externalIdPrimary,
		public Identifier $externalIdSecondary,
		public Code $externalCodePrimary,
	) {}

	public static function with(
		Identifier $id,
		Code|int $ledger,
		Code|int $code,
		int $flags = 0,
		?Identifier $externalIdPrimary = null,
		?Identifier $externalIdSecondary = null,
		Code|int $externalCodePrimary = 1,
	): self {
		return new self(
			id: $id,
			ledger: \is_int($ledger) ? Code::of($ledger) : $ledger,
			code: \is_int($code) ? Code::of($code) : $code,
			flags: AccountFlags::of($flags),
			externalIdPrimary: $externalIdPrimary ?? Identifier::zero(),
			externalIdSecondary: $externalIdSecondary ?? Identifier::zero(),
			externalCodePrimary: \is_int($externalCodePrimary) ? Code::of($externalCodePrimary) : $externalCodePrimary,
		);
	}
}
