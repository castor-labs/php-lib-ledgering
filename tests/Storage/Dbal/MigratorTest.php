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

use Castor\Ledgering\Infra\Database;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class MigratorTest extends TestCase
{
	#[Test]
	public function it_creates_database_schema(): void
	{
		$connection = Database::connection();
		$schemaManager = $connection->createSchemaManager();

		// Verify all tables exist
		self::assertTrue($schemaManager->tablesExist(['ledgering_accounts']));
		self::assertTrue($schemaManager->tablesExist(['ledgering_transfers']));
		self::assertTrue($schemaManager->tablesExist(['ledgering_account_balances']));
	}

	#[Test]
	public function it_has_correct_accounts_table_structure(): void
	{
		$connection = Database::connection();
		$schemaManager = $connection->createSchemaManager();
		$table = $schemaManager->introspectTableByUnquotedName('ledgering_accounts');

		// Verify primary key is sequence
		$primaryKey = $table->getPrimaryKeyConstraint();
		self::assertNotNull($primaryKey);
		self::assertSame(['sequence'], \array_map(static fn(UnqualifiedName $n) => $n->getIdentifier()->getValue(), $primaryKey->getColumnNames()));

		// Verify columns exist
		self::assertTrue($table->hasColumn('sequence'));
		self::assertTrue($table->hasColumn('id'));
		self::assertTrue($table->hasColumn('ledger'));
		self::assertTrue($table->hasColumn('code'));
		self::assertTrue($table->hasColumn('flags'));
		self::assertTrue($table->hasColumn('external_id_primary'));
		self::assertTrue($table->hasColumn('external_id_secondary'));
		self::assertTrue($table->hasColumn('external_code_primary'));
		self::assertTrue($table->hasColumn('debits_posted'));
		self::assertTrue($table->hasColumn('credits_posted'));
		self::assertTrue($table->hasColumn('debits_pending'));
		self::assertTrue($table->hasColumn('credits_pending'));
		self::assertTrue($table->hasColumn('timestamp_seconds'));
		self::assertTrue($table->hasColumn('timestamp_nanos'));

		// Verify id has unique index
		self::assertTrue($table->hasIndex('uniq_accounts_id'));
		$idIndex = $table->getIndex('uniq_accounts_id');
		self::assertSame(IndexType::UNIQUE, $idIndex->getType());

		// Verify other indexes
		self::assertTrue($table->hasIndex('idx_accounts_external_id_primary'));
		self::assertTrue($table->hasIndex('idx_accounts_external_id_secondary'));
		self::assertTrue($table->hasIndex('idx_accounts_ledger_code'));
		self::assertTrue($table->hasIndex('idx_accounts_timestamp'));
	}

	#[Test]
	public function it_has_correct_transfers_table_structure(): void
	{
		$connection = Database::connection();
		$schemaManager = $connection->createSchemaManager();
		$table = $schemaManager->introspectTableByUnquotedName('ledgering_transfers');

		// Verify primary key is sequence
		$primaryKey = $table->getPrimaryKeyConstraint();
		self::assertNotNull($primaryKey);
		self::assertSame(['sequence'], \array_map(static fn(UnqualifiedName $n) => $n->getIdentifier()->getValue(), $primaryKey->getColumnNames()));

		// Verify columns exist
		self::assertTrue($table->hasColumn('sequence'));
		self::assertTrue($table->hasColumn('id'));
		self::assertTrue($table->hasColumn('debit_account_id'));
		self::assertTrue($table->hasColumn('credit_account_id'));
		self::assertTrue($table->hasColumn('amount'));
		self::assertTrue($table->hasColumn('pending_id'));
		self::assertTrue($table->hasColumn('ledger'));
		self::assertTrue($table->hasColumn('code'));
		self::assertTrue($table->hasColumn('flags'));
		self::assertTrue($table->hasColumn('timeout_seconds'));
		self::assertTrue($table->hasColumn('timestamp_seconds'));
		self::assertTrue($table->hasColumn('timestamp_nanos'));

		// Verify id has unique index
		self::assertTrue($table->hasIndex('uniq_transfers_id'));
		$idIndex = $table->getIndex('uniq_transfers_id');
		self::assertSame(IndexType::UNIQUE, $idIndex->getType());

		// Verify other indexes
		self::assertTrue($table->hasIndex('idx_transfers_debit_account'));
		self::assertTrue($table->hasIndex('idx_transfers_credit_account'));
		self::assertTrue($table->hasIndex('idx_transfers_pending_id'));
		self::assertTrue($table->hasIndex('idx_transfers_timestamp'));
	}

	#[Test]
	public function it_has_correct_account_balances_table_structure(): void
	{
		$connection = Database::connection();
		$schemaManager = $connection->createSchemaManager();
		$table = $schemaManager->introspectTableByUnquotedName('ledgering_account_balances');

		// Verify primary key is sequence
		$primaryKey = $table->getPrimaryKeyConstraint();
		self::assertNotNull($primaryKey);
		self::assertSame(['sequence'], \array_map(static fn(UnqualifiedName $n) => $n->getIdentifier()->getValue(), $primaryKey->getColumnNames()));

		// Verify columns exist
		self::assertTrue($table->hasColumn('sequence'));
		self::assertTrue($table->hasColumn('account_id'));
		self::assertTrue($table->hasColumn('debits_posted'));
		self::assertTrue($table->hasColumn('credits_posted'));
		self::assertTrue($table->hasColumn('debits_pending'));
		self::assertTrue($table->hasColumn('credits_pending'));
		self::assertTrue($table->hasColumn('timestamp_seconds'));
		self::assertTrue($table->hasColumn('timestamp_nanos'));

		// Verify indexes
		self::assertTrue($table->hasIndex('idx_balances_account_timestamp'));
	}
}
