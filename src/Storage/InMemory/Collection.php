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

namespace Castor\Ledgering\Storage\InMemory;

use Castor\Ledgering\Storage\InvalidResult;
use Castor\Ledgering\Storage\Reader;

/**
 * Base immutable collection implementation.
 *
 * All methods that return self create new instances without mutating the original.
 *
 * @template T
 *
 * @implements Reader<T>
 */
abstract class Collection implements Reader
{
	/**
	 * @param array<T> $items
	 */
	final public function __construct(
		protected array $items = [],
	) {}

	#[\Override]
	public function toList(): array
	{
		return $this->items;
	}

	#[\Override]
	public function toIterator(): \Iterator
	{
		return new \ArrayIterator($this->items);
	}

	#[\Override]
	public function toMap(callable $keyFn): array
	{
		$map = [];
		foreach ($this->items as $item) {
			$key = $keyFn($item);
			$map[$key] = $item;
		}

		return $map;
	}

	#[\Override]
	public function toListMap(callable $keyFn): array
	{
		$map = [];
		foreach ($this->items as $item) {
			$key = $keyFn($item);
			$map[$key][] = $item;
		}

		return $map;
	}

	#[\Override]
	public function slice(int $offset, int $limit = 0): static
	{
		$sliced = \array_slice($this->items, $offset, $limit === 0 ? null : $limit);

		$clone = clone $this;
		$clone->items = $sliced;

		return $clone;
	}

	#[\Override]
	public function count(): int
	{
		return \count($this->items);
	}

	#[\Override]
	public function first(): mixed
	{
		return $this->items[0] ?? null;
	}

	#[\Override]
	public function one(): mixed
	{
		$count = $this->count();

		if ($count === 0) {
			throw InvalidResult::emptyReader();
		}

		if ($count > 1) {
			throw InvalidResult::multipleItemsFound($count);
		}

		return $this->items[0];
	}

	#[\Override]
	public function pick(int $n): array
	{
		$count = $this->count();

		if ($count !== $n) {
			throw InvalidResult::unexpectedItemCount($n, $count);
		}

		return $this->items;
	}

	/**
	 * Filter items using a predicate function.
	 *
	 * Returns a new collection with items that match the predicate.
	 * Does not mutate the original collection.
	 *
	 * @param callable(T): bool $predicate
	 */
	protected function filter(callable $predicate): static
	{
		$filtered = \array_values(\array_filter($this->items, $predicate));

		$clone = clone $this;
		$clone->items = $filtered;

		return $clone;
	}
}
