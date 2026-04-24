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
