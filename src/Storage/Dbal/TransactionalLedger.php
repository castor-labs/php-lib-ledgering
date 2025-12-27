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

namespace Castor\Ledgering\Storage\Dbal;

use Castor\Ledgering\CreateAccount;
use Castor\Ledgering\CreateTransfer;
use Castor\Ledgering\ExpirePendingTransfers;
use Castor\Ledgering\Ledger;
use Doctrine\DBAL\Connection;

/**
 * Transactional ledger decorator.
 *
 * Wraps another Ledger implementation and executes all operations
 * within a database transaction, ensuring atomicity.
 *
 * If any operation fails, the entire transaction is rolled back.
 */
final readonly class TransactionalLedger implements Ledger
{
	public function __construct(
		private Connection $connection,
		private Ledger $ledger,
	) {}

	#[\Override]
	public function execute(CreateAccount|CreateTransfer|ExpirePendingTransfers ...$commands): void
	{
		$this->connection->transactional(function () use ($commands): void {
			$this->ledger->execute(...$commands);
		});
	}
}
