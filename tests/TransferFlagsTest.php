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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TransferFlagsTest extends TestCase
{
	#[Test]
	public function it_creates_none_flags(): void
	{
		$flags = TransferFlags::none();

		self::assertSame(0, $flags->toInt());
	}

	#[Test]
	public function it_creates_pending_transfer(): void
	{
		$flags = TransferFlags::of(TransferFlags::PENDING);

		self::assertTrue($flags->isPending());
	}

	#[Test]
	public function it_throws_when_pending_combined_with_post_pending(): void
	{
		$this->expectException(ConstraintViolation::class);
		$this->expectExceptionCode(ErrorCode::InvalidFlags->value);
		$this->expectExceptionMessage('PENDING cannot be combined with POST_PENDING or VOID_PENDING');

		TransferFlags::of(TransferFlags::PENDING | TransferFlags::POST_PENDING);
	}

	#[Test]
	public function it_throws_when_pending_combined_with_void_pending(): void
	{
		$this->expectException(ConstraintViolation::class);
		$this->expectExceptionCode(ErrorCode::InvalidFlags->value);
		$this->expectExceptionMessage('PENDING cannot be combined with POST_PENDING or VOID_PENDING');

		TransferFlags::of(TransferFlags::PENDING | TransferFlags::VOID_PENDING);
	}

	#[Test]
	public function it_throws_when_post_and_void_pending_combined(): void
	{
		$this->expectException(ConstraintViolation::class);
		$this->expectExceptionCode(ErrorCode::InvalidFlags->value);
		$this->expectExceptionMessage('Flags POST_PENDING, VOID_PENDING are mutually exclusive');

		TransferFlags::of(TransferFlags::POST_PENDING | TransferFlags::VOID_PENDING);
	}

	#[Test]
	public function it_throws_when_post_pending_combined_with_balancing(): void
	{
		$this->expectException(ConstraintViolation::class);
		$this->expectExceptionCode(ErrorCode::InvalidFlags->value);
		$this->expectExceptionMessage('POST_PENDING and VOID_PENDING cannot be combined with balancing flags');

		TransferFlags::of(TransferFlags::POST_PENDING | TransferFlags::BALANCING_DEBIT);
	}

	#[Test]
	public function it_throws_when_closing_without_pending(): void
	{
		$this->expectException(ConstraintViolation::class);
		$this->expectExceptionCode(ErrorCode::InvalidFlags->value);
		$this->expectExceptionMessage('CLOSING_DEBIT and CLOSING_CREDIT require PENDING flag');

		TransferFlags::of(TransferFlags::CLOSING_DEBIT);
	}

	#[Test]
	public function it_allows_closing_with_pending(): void
	{
		$flags = TransferFlags::of(TransferFlags::PENDING | TransferFlags::CLOSING_DEBIT);

		self::assertTrue($flags->isPending());
		self::assertTrue($flags->isClosingDebit());
	}

	#[Test]
	public function it_checks_all_flag_types(): void
	{
		self::assertTrue(TransferFlags::of(TransferFlags::PENDING)->isPending());
		self::assertTrue(TransferFlags::of(TransferFlags::POST_PENDING)->isPostPending());
		self::assertTrue(TransferFlags::of(TransferFlags::VOID_PENDING)->isVoidPending());
		self::assertTrue(TransferFlags::of(TransferFlags::BALANCING_DEBIT)->isBalancingDebit());
		self::assertTrue(TransferFlags::of(TransferFlags::BALANCING_CREDIT)->isBalancingCredit());
		self::assertTrue(TransferFlags::of(TransferFlags::PENDING | TransferFlags::CLOSING_DEBIT)->isClosingDebit());
		self::assertTrue(TransferFlags::of(TransferFlags::PENDING | TransferFlags::CLOSING_CREDIT)->isClosingCredit());
	}

	#[Test]
	public function it_adds_and_removes_flags(): void
	{
		$flags = TransferFlags::none()
			->with(TransferFlags::PENDING)
			->with(TransferFlags::BALANCING_DEBIT);

		self::assertTrue($flags->isPending());
		self::assertTrue($flags->isBalancingDebit());

		$flags = $flags->without(TransferFlags::BALANCING_DEBIT);

		self::assertFalse($flags->isBalancingDebit());
	}

	#[Test]
	public function it_checks_equality(): void
	{
		$a = TransferFlags::of(TransferFlags::PENDING);
		$b = TransferFlags::of(TransferFlags::PENDING);
		$c = TransferFlags::of(TransferFlags::POST_PENDING);

		self::assertTrue($a->equals($b));
		self::assertFalse($a->equals($c));
	}
}
