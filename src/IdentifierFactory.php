<?php

declare(strict_types=1);

namespace Castor\Ledgering;

/**
 * Creates new Identifier instances.
 *
 * This is the preferred way to generate identifiers for accounts and transfers
 * in this library. Using a factory allows for time-ordered, monotonic identifier
 * generation, which ensures optimal performance for database indexing and
 * querying, and enables cursor-based pagination using well-known identifiers.
 *
 * @see TimeOrderedMonotonic for the recommended implementation
 */
interface IdentifierFactory
{
	/**
	 * Create a new unique identifier.
	 */
	public function create(): Identifier;
}
