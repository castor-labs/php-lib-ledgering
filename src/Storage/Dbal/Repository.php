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

namespace Castor\Ledgering\Storage\Dbal;

use Castor\Ledgering\Storage\InvalidResult;
use Castor\Ledgering\Storage\Reader;
use Castor\Ledgering\Storage\UnexpectedError;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Type;

/**
 * Base DBAL repository implementation with immutable query building.
 *
 * All filtering methods return new instances without mutating the original.
 *
 * @template T
 *
 * @implements Reader<T>
 */
abstract class Repository implements Reader
{
	/**
	 * @param Connection $connection The database connection
	 * @param QueryBuilder $qb The query builder for this repository
	 * @param AbstractPlatform $platform The database platform
	 * @param array<string, string> $typeMap Map of column names to Doctrine type names for automatic conversion
	 */
	public function __construct(
		protected Connection $connection,
		private QueryBuilder $qb,
		private readonly AbstractPlatform $platform,
		private readonly array $typeMap = [],
	) {}

	/**
	 * Clone the query builder when cloning the reader.
	 */
	public function __clone()
	{
		$this->qb = clone $this->qb;
	}

	#[\Override]
	public function toList(): array
	{
		try {
			$rows = $this->qb->executeQuery()->fetchAllAssociative();

			return \array_map(fn(array $row) => $this->hydrate($this->convertRow($row)), $rows);
		} catch (\Throwable $e) {
			throw new UnexpectedError($e->getMessage(), (int) $e->getCode(), $e);
		}
	}

	#[\Override]
	public function toIterator(): \Iterator
	{
		return new \ArrayIterator($this->toList());
	}

	#[\Override]
	public function toMap(callable $keyFn): array
	{
		$map = [];
		foreach ($this->toList() as $item) {
			$key = $keyFn($item);
			$map[$key] = $item;
		}

		return $map;
	}

	#[\Override]
	public function toListMap(callable $keyFn): array
	{
		$map = [];
		foreach ($this->toList() as $item) {
			$key = $keyFn($item);
			$map[$key][] = $item;
		}

		return $map;
	}

	#[\Override]
	public function slice(int $offset, int $limit = 0): static
	{
		$clone = clone $this;

		// Convert 0-based offset to 1-based sequence (sequences start at 1)
		$clone->qb->andWhere('sequence > :offset')
			->setParameter('offset', $offset);

		if ($limit > 0) {
			$clone->qb->setMaxResults($limit);
		}

		return $clone;
	}

	#[\Override]
	public function count(): int
	{
		try {
			$qb = clone $this->qb;
			$qb->select('COUNT(*)')
				->resetOrderBy();

			return (int) $qb->executeQuery()->fetchOne();
		} catch (\Throwable $e) {
			throw new UnexpectedError($e->getMessage(), (int) $e->getCode(), $e);
		}
	}

	#[\Override]
	public function first(): mixed
	{
		try {
			$qb = clone $this->qb;
			$qb->setMaxResults(1);

			$row = $qb->executeQuery()->fetchAssociative();

			return $row !== false ? $this->hydrate($this->convertRow($row)) : null;
		} catch (\Throwable $e) {
			throw new UnexpectedError($e->getMessage(), (int) $e->getCode(), $e);
		}
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

		return $this->first();
	}

	#[\Override]
	public function pick(int $n): array
	{
		$items = $this->toList();
		$count = \count($items);

		if ($count !== $n) {
			throw InvalidResult::unexpectedItemCount($n, $count);
		}

		return $items;
	}

	/**
	 * Apply a filter to the query builder.
	 *
	 * Returns a new instance with the filter applied.
	 *
	 * @param callable(QueryBuilder): void $fn Callback that receives the QueryBuilder to apply filters
	 */
	protected function filter(callable $fn): static
	{
		$clone = clone $this;
		$fn($clone->qb);

		return $clone;
	}

	/**
	 * Hydrate a database row into a domain object.
	 *
	 * Row values are already converted to PHP types via the type map.
	 *
	 * @param array<string, mixed> $row
	 *
	 * @return T
	 */
	abstract protected function hydrate(array $row): mixed;

	/**
	 * Convert database row values to PHP values using the type map.
	 *
	 * @param array<string, mixed> $row
	 *
	 * @return array<string, mixed>
	 */
	private function convertRow(array $row): array
	{
		foreach ($this->typeMap as $column => $typeName) {
			if (!\array_key_exists($column, $row)) {
				continue;
			}

			$type = Type::getType($typeName);
			$row[$column] = $type->convertToPHPValue($row[$column], $this->platform);
		}

		return $row;
	}
}
