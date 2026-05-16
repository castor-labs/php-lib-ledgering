<?php

declare(strict_types=1);

namespace Castor\Ledgering\Storage;

/**
 * Exception thrown when the result of a storage operation is invalid.
 */
final class InvalidResult extends \Exception
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
