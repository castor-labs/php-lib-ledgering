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

use Castor\Ledgering\CreateAccount;
use Castor\Ledgering\CreateTransfer;
use Castor\Ledgering\ExpirePendingTransfers;
use Castor\Ledgering\Ledger;
use Castor\Ledgering\Storage\UnexpectedError;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\TransactionIsolationLevel;

/**
 * Transactional ledger decorator.
 *
 * Wraps another Ledger implementation and executes all operations
 * within a database transaction, ensuring atomicity.
 *
 * If any operation fails, the entire transaction is rolled back.
 *
 * The transaction isolation level is set to REPEATABLE_READ by default but can be changed.
 *
 * In any case, we DO NOT recommend anything other than REPEATABLE_READ or SERIALIZABLE (for maximum consistency).
 * READ_COMMITED and READ_UNCOMMITTED are not enough for the level of safety required by this library.
 *
 * The isolation level IS NOT set back to the original value after the transaction is committed, because of performance
 * reasons. For this reason, we recommend the connection you use for the ledger is different to the one you use for
 * your application code (even when the database is the same), as your code will likely need different levels of isolation.
 *
 * You MAY create a custom decorator that returns the isolation level to the original value after the transaction is committed,
 * if you wish to use the same connection.
 *
 * It's best practice to use a separate connection for the ledger: this also ensures you use it correctly in your
 * application code, since the two different connections force you to consider a network partition between your application
 * code and your ledger code.
 */
final readonly class TransactionalLedger implements Ledger
{
	public function __construct(
		private Connection $connection,
		private Ledger $ledger,
		private TransactionIsolationLevel $isolationLevel = TransactionIsolationLevel::REPEATABLE_READ,
	) {}

	#[\Override]
	public function execute(CreateAccount|CreateTransfer|ExpirePendingTransfers ...$commands): void
	{
		try {
			$this->ensureCorrectIsolationLevel();
		} catch (Exception $e) {
			throw new UnexpectedError('Could not ensure correct isolation level', previous: $e);
		}

		$this->connection->transactional(function () use ($commands): void {
			$this->ledger->execute(...$commands);
		});
	}

	/**
	 * @throws Exception
	 */
	private function ensureCorrectIsolationLevel(): void
	{
		$isolationLevel = $this->connection->getTransactionIsolation();

		if ($isolationLevel !== $this->isolationLevel) {
			$this->connection->setTransactionIsolation($this->isolationLevel);
		}
	}
}
