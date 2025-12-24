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
 * Represents a reader for items that can be transformed and iterated.
 *
 * @template T
 */
interface Reader
{
	/**
	 * Convert the collection to an array.
	 *
	 * @return array<T>
	 */
	public function toList(): array;

	/**
	 * Convert the collection to an iterator.
	 *
	 * @return \Iterator<int, T>
	 */
	public function toIterator(): \Iterator;

	/**
	 * Convert the collection to a map using a key function.
	 *
	 * The key function receives an item and returns a string key.
	 *
	 * @param callable(T): string $keyFn
	 *
	 * @return array<string, T>
	 */
	public function toMap(callable $keyFn): array;

	/**
	 * Convert the collection to a map of lists using a key function.
	 *
	 * The key function receives an item and returns a string key.
	 * Items with the same key are grouped together in an array.
	 *
	 * @param callable(T): string $keyFn
	 *
	 * @return array<string, array<T>>
	 */
	public function toListMap(callable $keyFn): array;

	/**
	 * Return a slice of the reader.
	 *
	 * @param int $offset Starting position (0-based)
	 * @param int $limit Maximum number of items (0 = no limit)
	 *
	 * @return Reader<T>
	 */
	public function slice(int $offset, int $limit = 0): self;

	/**
	 * Get the number of items in the reader.
	 */
	public function count(): int;

	/**
	 * Get the first item in the reader, or null if empty.
	 *
	 * @return T|null
	 */
	public function first(): mixed;

	/**
	 * Get exactly one item from the reader.
	 *
	 * Throws an exception if the reader is empty or contains more than one item.
	 *
	 * @return T
	 *
	 * @throws StorageError if the reader is empty or has more than one item
	 */
	public function one(): mixed;

	/**
	 * Get exactly n items from the reader.
	 *
	 * Throws an exception if the reader does not contain exactly n items.
	 *
	 * @param int $n Number of items to pick
	 *
	 * @return array<T>
	 *
	 * @throws StorageError if the reader does not contain exactly n items
	 */
	public function pick(int $n): array;
}
