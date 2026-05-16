<?php

declare(strict_types=1);

namespace Castor\Ledgering;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IdentifierTest extends TestCase
{
	#[Test]
	public function it_creates_zero_identifier(): void
	{
		$id = Identifier::zero();

		self::assertTrue($id->isZero());
		self::assertSame(16, \strlen($id->bytes));
	}

	#[Test]
	public function it_creates_from_bytes(): void
	{
		$bytes = \random_bytes(16);
		$id = Identifier::fromBytes($bytes);

		self::assertSame($bytes, $id->bytes);
	}

	#[Test]
	public function it_throws_on_invalid_byte_length(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('must be exactly 16 bytes');

		Identifier::fromBytes('short');
	}

	#[Test]
	public function it_creates_from_hex_string(): void
	{
		$hex = '0123456789abcdef0123456789abcdef';
		$id = Identifier::fromHex($hex);

		self::assertSame($hex, $id->toHex());
	}

	#[Test]
	public function it_creates_from_uuid_format(): void
	{
		$uuid = '01234567-89ab-cdef-0123-456789abcdef';
		$id = Identifier::fromHex($uuid);

		self::assertSame('0123456789abcdef0123456789abcdef', $id->toHex());
		self::assertSame($uuid, $id->toUuid());
	}

	#[Test]
	public function it_throws_on_invalid_hex_length(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('must be exactly 32 characters');

		Identifier::fromHex('short');
	}

	#[Test]
	public function it_throws_on_invalid_hex_characters(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Failed to decode hex string');

		@Identifier::fromHex('zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz');
	}

	#[Test]
	public function it_converts_to_hex(): void
	{
		$bytes = \hex2bin('0123456789abcdef0123456789abcdef');
		\assert(\is_string($bytes));
		$id = Identifier::fromBytes($bytes);

		self::assertSame('0123456789abcdef0123456789abcdef', $id->toHex());
	}

	#[Test]
	public function it_converts_to_uuid(): void
	{
		$id = Identifier::fromHex('0123456789abcdef0123456789abcdef');

		self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $id->toUuid());
	}

	#[Test]
	public function it_checks_equality(): void
	{
		$a = Identifier::fromHex('0123456789abcdef0123456789abcdef');
		$b = Identifier::fromHex('01234567-89ab-cdef-0123-456789abcdef');
		$c = Identifier::fromHex('ffffffffffffffffffffffffffffffff');

		self::assertTrue($a->equals($b));
		self::assertFalse($a->equals($c));
	}

	#[Test]
	public function it_converts_to_string_as_uuid(): void
	{
		$id = Identifier::fromHex('0123456789abcdef0123456789abcdef');

		self::assertSame('01234567-89ab-cdef-0123-456789abcdef', (string) $id);
	}

	#[Test]
	public function it_checks_if_zero(): void
	{
		$zero = Identifier::zero();
		$nonZero = Identifier::fromHex('0123456789abcdef0123456789abcdef');

		self::assertTrue($zero->isZero());
		self::assertFalse($nonZero->isZero());
	}
}
