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

final class BalanceTest extends TestCase
{
	#[Test]
	public function it_creates_balance_with_amounts(): void
	{
		$debitsPosted = Amount::of(1000);
		$creditsPosted = Amount::of(500);
		$debitsPending = Amount::of(200);
		$creditsPending = Amount::of(100);

		$balance = new Balance($debitsPosted, $creditsPosted, $debitsPending, $creditsPending);

		self::assertSame(1000, $balance->debitsPosted->value);
		self::assertSame(500, $balance->creditsPosted->value);
		self::assertSame(200, $balance->debitsPending->value);
		self::assertSame(100, $balance->creditsPending->value);
	}

	#[Test]
	public function it_creates_zero_balance(): void
	{
		$balance = Balance::zero();

		self::assertSame(0, $balance->debitsPosted->value);
		self::assertSame(0, $balance->creditsPosted->value);
		self::assertSame(0, $balance->debitsPending->value);
		self::assertSame(0, $balance->creditsPending->value);
	}
}
