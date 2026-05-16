<?php

declare(strict_types=1);

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
