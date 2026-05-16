<?php

declare(strict_types=1);

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
