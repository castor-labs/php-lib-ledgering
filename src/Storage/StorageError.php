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

namespace Castor\Ledgering\Storage;

/**
 * Exception thrown when a storage operation fails.
 */
final class StorageError extends \RuntimeException
{
	public static function emptyReader(): self
	{
		return new self('Reader is empty');
	}

	public static function multipleItemsFound(int $count): self
	{
		return new self(\sprintf('Expected exactly one item, found %d', $count));
	}

	public static function unexpectedItemCount(int $expected, int $actual): self
	{
		return new self(\sprintf('Expected exactly %d items, found %d', $expected, $actual));
	}
}
