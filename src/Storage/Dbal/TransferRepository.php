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

use Castor\Ledgering\Amount;
use Castor\Ledgering\Code;
use Castor\Ledgering\ConstraintViolation;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\Storage\TransferReader;
use Castor\Ledgering\Storage\TransferWriter;
use Castor\Ledgering\Storage\UnexpectedError;
use Castor\Ledgering\Time\Duration;
use Castor\Ledgering\Time\Instant;
use Castor\Ledgering\Transfer;
use Castor\Ledgering\TransferFlags;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * DBAL-based transfer repository.
 *
 * Read operations return immutable filtered views.
 * Write operations persist to the database.
 *
 * @extends Repository<Transfer>
 *
 * @phpstan-type TransferRow array{
 *     sequence: int,
 *     id: string,
 *     debit_account_id: string,
 *     credit_account_id: string,
 *     amount: int,
 *     pending_id: string,
 *     ledger: int,
 *     code: int,
 *     flags: int,
 *     timeout_seconds: int,
 *     external_id_primary: string,
 *     external_id_secondary: string,
 *     external_code_primary: int,
 *     timestamp_seconds: int,
 *     timestamp_nanos: int
 * }
 */
final class TransferRepository extends Repository implements TransferReader, TransferWriter
{
	private const array TYPE_MAP = [
		'id' => 'binary',
		'debit_account_id' => 'binary',
		'credit_account_id' => 'binary',
		'pending_id' => 'binary',
		'external_id_primary' => 'binary',
		'external_id_secondary' => 'binary',
	];

	public function __construct(
		Connection $connection,
	) {
		parent::__construct(
			$connection,
			$connection->createQueryBuilder()->select('*')->from('ledgering_transfers'),
			$connection->getDatabasePlatform(),
			self::TYPE_MAP,
		);
	}

	#[\Override]
	public function ofId(Identifier ...$ids): self
	{
		return $this->filter(static function (QueryBuilder $qb) use ($ids): void {
			$bytes = \array_map(static fn(Identifier $id) => $id->bytes, $ids);
			$qb->andWhere($qb->expr()->in('id', ':ids'))
				->setParameter('ids', $bytes, ArrayParameterType::BINARY);
		});
	}

	#[\Override]
	public function ofExternalIdPrimary(Identifier ...$ids): self
	{
		return $this->filter(static function (QueryBuilder $qb) use ($ids): void {
			$bytes = \array_map(static fn(Identifier $id) => $id->bytes, $ids);
			$qb->andWhere($qb->expr()->in('external_id_primary', ':external_ids_primary'))
				->setParameter('external_ids_primary', $bytes, ArrayParameterType::BINARY);
		});
	}

	#[\Override]
	public function ofExternalIdSecondary(Identifier ...$ids): self
	{
		return $this->filter(static function (QueryBuilder $qb) use ($ids): void {
			$bytes = \array_map(static fn(Identifier $id) => $id->bytes, $ids);
			$qb->andWhere($qb->expr()->in('external_id_secondary', ':external_ids_secondary'))
				->setParameter('external_ids_secondary', $bytes, ArrayParameterType::BINARY);
		});
	}

	#[\Override]
	public function ofDebitAccount(Identifier ...$ids): self
	{
		return $this->filter(static function (QueryBuilder $qb) use ($ids): void {
			$bytes = \array_map(static fn(Identifier $id) => $id->bytes, $ids);
			$qb->andWhere($qb->expr()->in('debit_account_id', ':debit_account_ids'))
				->setParameter('debit_account_ids', $bytes, ArrayParameterType::BINARY);
		});
	}

	#[\Override]
	public function ofCreditAccount(Identifier ...$ids): self
	{
		return $this->filter(static function (QueryBuilder $qb) use ($ids): void {
			$bytes = \array_map(static fn(Identifier $id) => $id->bytes, $ids);
			$qb->andWhere($qb->expr()->in('credit_account_id', ':credit_account_ids'))
				->setParameter('credit_account_ids', $bytes, ArrayParameterType::BINARY);
		});
	}

	#[\Override]
	public function ofPendingId(Identifier ...$ids): self
	{
		return $this->filter(static function (QueryBuilder $qb) use ($ids): void {
			$bytes = \array_map(static fn(Identifier $id) => $id->bytes, $ids);
			$qb->andWhere($qb->expr()->in('pending_id', ':pending_ids'))
				->setParameter('pending_ids', $bytes, ArrayParameterType::BINARY);
		});
	}

	#[\Override]
	public function expired(Instant $now): self
	{
		return $this->filter(static function (QueryBuilder $qb) use ($now): void {
			// Must be a pending transfer (PENDING flag set)
			$pendingFlag = TransferFlags::PENDING;
			$qb->andWhere('(flags & :pending_flag) = :pending_flag')
				->setParameter('pending_flag', $pendingFlag, ParameterType::INTEGER);

			// Must have a non-zero timeout
			$qb->andWhere('timeout_seconds > 0');

			// Check if expired: (timestamp_seconds + timeout_seconds) <= now
			$qb->andWhere('(timestamp_seconds + timeout_seconds) <= :now_seconds')
				->setParameter('now_seconds', $now->seconds, ParameterType::INTEGER);

			// Must not have been posted or voided
			// Check that no other transfer exists with POST_PENDING or VOID_PENDING flags
			// that references this transfer's ID via pending_id
			$postPendingFlag = TransferFlags::POST_PENDING;
			$voidPendingFlag = TransferFlags::VOID_PENDING;

			$qb->andWhere(
				'NOT EXISTS ('.
				'SELECT 1 FROM ledgering_transfers t2 '.
				'WHERE t2.pending_id = ledgering_transfers.id '.
				'AND ((t2.flags & :post_pending_flag) = :post_pending_flag '.
				'OR (t2.flags & :void_pending_flag) = :void_pending_flag)'.
				')',
			)
				->setParameter('post_pending_flag', $postPendingFlag, ParameterType::INTEGER)
				->setParameter('void_pending_flag', $voidPendingFlag, ParameterType::INTEGER);
		});
	}

	#[\Override]
	public function write(Transfer $transfer): void
	{
		try {
			$data = [
				'id' => $transfer->id->bytes,
				'debit_account_id' => $transfer->debitAccountId->bytes,
				'credit_account_id' => $transfer->creditAccountId->bytes,
				'amount' => $transfer->amount->value,
				'pending_id' => $transfer->pendingId->bytes,
				'ledger' => $transfer->ledger->value,
				'code' => $transfer->code->value,
				'flags' => $transfer->flags->toInt(),
				'timeout_seconds' => $transfer->timeout->seconds,
				'external_id_primary' => $transfer->externalIdPrimary->bytes,
				'external_id_secondary' => $transfer->externalIdSecondary->bytes,
				'external_code_primary' => $transfer->externalCodePrimary->value,
				'timestamp_seconds' => $transfer->timestamp->seconds,
				'timestamp_nanos' => $transfer->timestamp->nano,
			];

			$types = [
				'id' => ParameterType::BINARY,
				'debit_account_id' => ParameterType::BINARY,
				'credit_account_id' => ParameterType::BINARY,
				'pending_id' => ParameterType::BINARY,
				'external_id_primary' => ParameterType::BINARY,
				'external_id_secondary' => ParameterType::BINARY,
			];

			// Transfers are immutable, so we only insert
			$this->connection->insert('ledgering_transfers', $data, $types);
		} catch (\Throwable $e) {
			throw new UnexpectedError($e->getMessage(), previous: $e);
		}
	}

	/**
	 * @param TransferRow $row
	 * @throws ConstraintViolation
	 */
	#[\Override]
	protected function hydrate(array $row): Transfer
	{
		return new Transfer(
			id: Identifier::fromBytes($row['id']),
			debitAccountId: Identifier::fromBytes($row['debit_account_id']),
			creditAccountId: Identifier::fromBytes($row['credit_account_id']),
			amount: Amount::of($row['amount']),
			pendingId: Identifier::fromBytes($row['pending_id']),
			ledger: Code::of($row['ledger']),
			code: Code::of($row['code']),
			flags: TransferFlags::of($row['flags']),
			timeout: Duration::of($row['timeout_seconds']),
			externalIdPrimary: Identifier::fromBytes($row['external_id_primary']),
			externalIdSecondary: Identifier::fromBytes($row['external_id_secondary']),
			externalCodePrimary: Code::of($row['external_code_primary']),
			timestamp: Instant::of($row['timestamp_seconds'], $row['timestamp_nanos']),
		);
	}
}
