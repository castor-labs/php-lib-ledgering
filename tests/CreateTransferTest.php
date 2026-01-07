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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CreateTransferTest extends TestCase
{
	#[Test]
	public function it_creates_command_with_required_fields(): void
	{
		$id = Identifier::fromHex('0123456789abcdef0123456789abcdef');
		$debitAccountId = Identifier::fromHex('11111111111111111111111111111111');
		$creditAccountId = Identifier::fromHex('22222222222222222222222222222222');
		$amount = Amount::of(1000);
		$ledger = Code::of(1);
		$code = Code::of(100);

		$command = CreateTransfer::with(
			id: $id,
			debitAccountId: $debitAccountId,
			creditAccountId: $creditAccountId,
			amount: $amount,
			ledger: $ledger,
			code: $code,
		);

		self::assertTrue($command->id->equals($id));
		self::assertTrue($command->debitAccountId->equals($debitAccountId));
		self::assertTrue($command->creditAccountId->equals($creditAccountId));
		self::assertSame(1000, $command->amount->value);
		self::assertTrue($command->ledger->equals($ledger));
		self::assertTrue($command->code->equals($code));
		self::assertTrue($command->flags->equals(TransferFlags::none()));
		self::assertTrue($command->pendingId->isZero());
		self::assertTrue($command->timeout->isZero());
	}

	#[Test]
	public function it_creates_command_with_optional_fields(): void
	{
		$id = Identifier::fromHex('0123456789abcdef0123456789abcdef');
		$debitAccountId = Identifier::fromHex('11111111111111111111111111111111');
		$creditAccountId = Identifier::fromHex('22222222222222222222222222222222');
		$amount = Amount::of(1000);
		$ledger = Code::of(1);
		$code = Code::of(100);
		$flags = TransferFlags::PENDING;
		$pendingId = Identifier::fromHex('33333333333333333333333333333333');
		$timeout = Duration::ofHours(24);
		$externalId = Identifier::fromHex('ffffffffffffffffffffffffffffffff');

		$command = CreateTransfer::with(
			id: $id,
			debitAccountId: $debitAccountId,
			creditAccountId: $creditAccountId,
			amount: $amount,
			ledger: $ledger,
			code: $code,
			flags: $flags,
			pendingId: $pendingId,
			timeout: $timeout,
			externalIdPrimary: $externalId,
		);

		self::assertSame(1, $command->flags->value);
		self::assertTrue($command->pendingId->equals($pendingId));
		self::assertSame(86400, $command->timeout->seconds);
		self::assertTrue($command->externalIdPrimary->equals($externalId));
	}
}
