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
 * Main entry point for ledger operations.
 *
 * Executes commands against the ledger using a command pattern.
 */
interface Ledger
{
	/**
	 * Execute one or more ledger commands atomically.
	 *
	 * @throws ConstraintViolation when a business rule is violated (e.g., insufficient funds, account not found)
	 * @throws Storage\UnexpectedError when a storage operation fails unexpectedly
	 */
	public function execute(CreateAccount|CreateTransfer|ExpirePendingTransfers ...$commands): void;
}
