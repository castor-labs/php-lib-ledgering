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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CreateAccountTest extends TestCase
{
	#[Test]
	public function it_creates_command_with_required_fields(): void
	{
		$id = Identifier::fromHex('0123456789abcdef0123456789abcdef');
		$ledger = Code::of(1);
		$code = Code::of(100);

		$command = CreateAccount::with($id, $ledger, $code);

		self::assertTrue($command->id->equals($id));
		self::assertTrue($command->ledger->equals($ledger));
		self::assertTrue($command->code->equals($code));
		self::assertTrue($command->flags->equals(AccountFlags::none()));
		self::assertTrue($command->externalIdPrimary->isZero());
		self::assertTrue($command->externalIdSecondary->isZero());
		self::assertSame(1, $command->externalCodePrimary->value);
	}

	#[Test]
	public function it_creates_command_with_optional_fields(): void
	{
		$id = Identifier::fromHex('0123456789abcdef0123456789abcdef');
		$ledger = Code::of(1);
		$code = Code::of(100);
		$flags = AccountFlags::HISTORY;
		$externalId = Identifier::fromHex('ffffffffffffffffffffffffffffffff');
		$externalCode = Code::of(999);

		$command = CreateAccount::with(
			id: $id,
			ledger: $ledger,
			code: $code,
			flags: $flags,
			externalIdPrimary: $externalId,
			externalCodePrimary: $externalCode,
		);

		self::assertSame(4, $command->flags->value);
		self::assertTrue($command->externalIdPrimary->equals($externalId));
		self::assertTrue($command->externalCodePrimary->equals($externalCode));
	}
}
