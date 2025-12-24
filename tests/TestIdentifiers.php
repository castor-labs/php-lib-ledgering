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

/**
 * Test helper class providing fixed identifiers for deterministic testing.
 */
final class TestIdentifiers
{
	// Account identifiers
	public static function accountOne(): Identifier
	{
		return Identifier::fromHex('00000000-0000-0000-0000-000000000001');
	}

	public static function accountTwo(): Identifier
	{
		return Identifier::fromHex('00000000-0000-0000-0000-000000000002');
	}

	public static function accountThree(): Identifier
	{
		return Identifier::fromHex('00000000-0000-0000-0000-000000000003');
	}

	public static function accountFour(): Identifier
	{
		return Identifier::fromHex('00000000-0000-0000-0000-000000000004');
	}

	public static function accountFive(): Identifier
	{
		return Identifier::fromHex('00000000-0000-0000-0000-000000000005');
	}

	public static function accountSix(): Identifier
	{
		return Identifier::fromHex('00000000-0000-0000-0000-000000000006');
	}

	// Transfer identifiers
	public static function transferOne(): Identifier
	{
		return Identifier::fromHex('10000000-0000-0000-0000-000000000001');
	}

	public static function transferTwo(): Identifier
	{
		return Identifier::fromHex('10000000-0000-0000-0000-000000000002');
	}

	public static function transferThree(): Identifier
	{
		return Identifier::fromHex('10000000-0000-0000-0000-000000000003');
	}

	public static function transferFour(): Identifier
	{
		return Identifier::fromHex('10000000-0000-0000-0000-000000000004');
	}

	public static function transferFive(): Identifier
	{
		return Identifier::fromHex('10000000-0000-0000-0000-000000000005');
	}

	public static function transferSix(): Identifier
	{
		return Identifier::fromHex('10000000-0000-0000-0000-000000000006');
	}

	public static function transferSeven(): Identifier
	{
		return Identifier::fromHex('10000000-0000-0000-0000-000000000007');
	}

	public static function transferEight(): Identifier
	{
		return Identifier::fromHex('10000000-0000-0000-0000-000000000008');
	}

	public static function transferNine(): Identifier
	{
		return Identifier::fromHex('10000000-0000-0000-0000-000000000009');
	}

	public static function transferTen(): Identifier
	{
		return Identifier::fromHex('10000000-0000-0000-0000-00000000000a');
	}

	// Pending transfer identifiers
	public static function pendingOne(): Identifier
	{
		return Identifier::fromHex('20000000-0000-0000-0000-000000000001');
	}

	public static function pendingTwo(): Identifier
	{
		return Identifier::fromHex('20000000-0000-0000-0000-000000000002');
	}

	public static function pendingThree(): Identifier
	{
		return Identifier::fromHex('20000000-0000-0000-0000-000000000003');
	}

	public static function pendingFour(): Identifier
	{
		return Identifier::fromHex('20000000-0000-0000-0000-000000000004');
	}

	public static function pendingFive(): Identifier
	{
		return Identifier::fromHex('20000000-0000-0000-0000-000000000005');
	}

	// Non-existent identifier for testing not found scenarios
	public static function nonExistent(): Identifier
	{
		return Identifier::fromHex('ffffffff-ffff-ffff-ffff-ffffffffffff');
	}
}
