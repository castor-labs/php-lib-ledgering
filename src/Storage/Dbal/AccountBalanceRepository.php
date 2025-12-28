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

use Castor\Ledgering\AccountBalance;
use Castor\Ledgering\Amount;
use Castor\Ledgering\Balance;
use Castor\Ledgering\ConstraintViolation;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\Storage\AccountBalanceReader;
use Castor\Ledgering\Storage\AccountBalanceWriter;
use Castor\Ledgering\Storage\UnexpectedError;
use Castor\Ledgering\Time\Instant;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * DBAL-based account balance repository.
 *
 * Read operations return immutable filtered views.
 * Write operations persist to the database.
 *
 * @extends Repository<AccountBalance>
 *
 * @phpstan-type AccountBalanceRow array{
 *     sequence: int,
 *     account_id: string,
 *     debits_posted: int,
 *     credits_posted: int,
 *     debits_pending: int,
 *     credits_pending: int,
 *     timestamp_seconds: int,
 *     timestamp_nanos: int
 * }
 */
final class AccountBalanceRepository extends Repository implements AccountBalanceReader, AccountBalanceWriter
{
	private const array TYPE_MAP = [
		'account_id' => 'binary',
	];

	public function __construct(
		Connection $connection,
	) {
		parent::__construct(
			$connection,
			$connection->createQueryBuilder()->select('*')->from('ledgering_account_balances'),
			$connection->getDatabasePlatform(),
			self::TYPE_MAP,
		);
	}

	#[\Override]
	public function ofAccountId(Identifier ...$ids): self
	{
		return $this->filter(static function (QueryBuilder $qb) use ($ids): void {
			$bytes = \array_map(static fn(Identifier $id) => $id->bytes, $ids);
			$qb->andWhere($qb->expr()->in('account_id', ':account_ids'))
				->setParameter('account_ids', $bytes, ArrayParameterType::BINARY);
		});
	}

	#[\Override]
	public function write(AccountBalance $balance): void
	{
		try {
			$data = [
				'account_id' => $balance->accountId->bytes,
				'debits_posted' => $balance->balance->debitsPosted->value,
				'credits_posted' => $balance->balance->creditsPosted->value,
				'debits_pending' => $balance->balance->debitsPending->value,
				'credits_pending' => $balance->balance->creditsPending->value,
				'timestamp_seconds' => $balance->timestamp->seconds,
				'timestamp_nanos' => $balance->timestamp->nano,
			];

			$types = [
				'account_id' => ParameterType::BINARY,
			];

			// Account balances are append-only (historical records)
			$this->connection->insert('ledgering_account_balances', $data, $types);
		} catch (\Throwable $e) {
			throw new UnexpectedError($e->getMessage(), previous: $e);
		}
	}

	/**
	 * @param AccountBalanceRow $row
	 * @throws ConstraintViolation
	 */
	#[\Override]
	protected function hydrate(array $row): AccountBalance
	{
		return new AccountBalance(
			accountId: Identifier::fromBytes($row['account_id']),
			balance: new Balance(
				debitsPosted: Amount::of($row['debits_posted']),
				creditsPosted: Amount::of($row['credits_posted']),
				debitsPending: Amount::of($row['debits_pending']),
				creditsPending: Amount::of($row['credits_pending']),
			),
			timestamp: Instant::of($row['timestamp_seconds'], $row['timestamp_nanos']),
		);
	}
}
