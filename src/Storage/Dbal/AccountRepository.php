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

use Castor\Ledgering\Account;
use Castor\Ledgering\AccountFlags;
use Castor\Ledgering\Amount;
use Castor\Ledgering\Balance;
use Castor\Ledgering\Code;
use Castor\Ledgering\ConstraintViolation;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\Storage\AccountReader;
use Castor\Ledgering\Storage\AccountWriter;
use Castor\Ledgering\Storage\UnexpectedError;
use Castor\Ledgering\Time\Instant;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * DBAL-based account repository.
 *
 * Read operations return immutable filtered views.
 * Write operations persist to the database.
 *
 * @extends Repository<Account>
 *
 * @phpstan-type AccountRow array{
 *     sequence: int,
 *     id: string,
 *     ledger: int,
 *     code: int,
 *     flags: int,
 *     external_id_primary: string,
 *     external_id_secondary: string,
 *     external_code_primary: int,
 *     debits_posted: int,
 *     credits_posted: int,
 *     debits_pending: int,
 *     credits_pending: int,
 *     timestamp_seconds: int,
 *     timestamp_nanos: int
 * }
 */
final class AccountRepository extends Repository implements AccountReader, AccountWriter
{
	private const array TYPE_MAP = [
		'id' => 'binary',
		'external_id_primary' => 'binary',
		'external_id_secondary' => 'binary',
	];

	public function __construct(
		Connection $connection,
	) {
		parent::__construct(
			$connection,
			$connection->createQueryBuilder()->select('*')->from('ledgering_accounts'),
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
	public function write(Account $account): void
	{
		try {
			$data = [
				'id' => $account->id->bytes,
				'ledger' => $account->ledger->value,
				'code' => $account->code->value,
				'flags' => $account->flags->toInt(),
				'external_id_primary' => $account->externalIdPrimary->bytes,
				'external_id_secondary' => $account->externalIdSecondary->bytes,
				'external_code_primary' => $account->externalCodePrimary->value,
				'debits_posted' => $account->balance->debitsPosted->value,
				'credits_posted' => $account->balance->creditsPosted->value,
				'debits_pending' => $account->balance->debitsPending->value,
				'credits_pending' => $account->balance->creditsPending->value,
				'timestamp_seconds' => $account->timestamp->seconds,
				'timestamp_nanos' => $account->timestamp->nano,
			];

			$types = [
				'id' => ParameterType::BINARY,
				'external_id_primary' => ParameterType::BINARY,
				'external_id_secondary' => ParameterType::BINARY,
			];

			// Try to update first
			$updated = $this->connection->update('ledgering_accounts', $data, ['id' => $account->id->bytes], $types);

			// If no rows updated, insert
			if ($updated === 0) {
				$this->connection->insert('ledgering_accounts', $data, $types);
			}
		} catch (\Throwable $e) {
			throw new UnexpectedError($e->getMessage(), previous: $e);
		}
	}

	/**
	 * @param AccountRow $row
	 * @throws ConstraintViolation
	 */
	#[\Override]
	protected function hydrate(array $row): Account
	{
		return new Account(
			id: Identifier::fromBytes($row['id']),
			ledger: Code::of($row['ledger']),
			code: Code::of($row['code']),
			flags: AccountFlags::of($row['flags']),
			externalIdPrimary: Identifier::fromBytes($row['external_id_primary']),
			externalIdSecondary: Identifier::fromBytes($row['external_id_secondary']),
			externalCodePrimary: Code::of($row['external_code_primary']),
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
