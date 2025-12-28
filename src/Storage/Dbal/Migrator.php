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

use Doctrine\DBAL\Schema\Schema;

/**
 * Database schema migrator for the ledgering system.
 *
 * Provides static methods to create and drop the required database tables.
 */
final class Migrator
{
	/**
	 * Create the ledgering schema.
	 *
	 * Creates three tables:
	 * - ledgering_accounts: Stores account information and current balances
	 * - ledgering_transfers: Stores all transfers between accounts
	 * - ledgering_account_balances: Stores historical balance snapshots (for accounts with HISTORY flag)
	 */
	public static function up(Schema $schema): void
	{
		$accounts = $schema->createTable('ledgering_accounts');
		$accounts->addColumn('sequence', 'bigint', ['unsigned' => true, 'autoincrement' => true]);
		$accounts->addColumn('id', 'binary', ['length' => 16, 'fixed' => true]);
		$accounts->addColumn('ledger', 'integer', ['unsigned' => true]);
		$accounts->addColumn('code', 'integer', ['unsigned' => true]);
		$accounts->addColumn('flags', 'integer', ['unsigned' => true]);
		$accounts->addColumn('external_id_primary', 'binary', ['length' => 16, 'fixed' => true]);
		$accounts->addColumn('external_id_secondary', 'binary', ['length' => 16, 'fixed' => true]);
		$accounts->addColumn('external_code_primary', 'integer', ['unsigned' => true]);
		$accounts->addColumn('debits_posted', 'bigint', ['unsigned' => true]);
		$accounts->addColumn('credits_posted', 'bigint', ['unsigned' => true]);
		$accounts->addColumn('debits_pending', 'bigint', ['unsigned' => true]);
		$accounts->addColumn('credits_pending', 'bigint', ['unsigned' => true]);
		$accounts->addColumn('timestamp_seconds', 'bigint', ['unsigned' => true]);
		$accounts->addColumn('timestamp_nanos', 'integer', ['unsigned' => true]);
		$accounts->setPrimaryKey(['sequence']);
		$accounts->addUniqueIndex(['id'], 'uniq_accounts_id');
		$accounts->addIndex(['external_id_primary'], 'idx_accounts_external_id_primary');
		$accounts->addIndex(['external_id_secondary'], 'idx_accounts_external_id_secondary');
		$accounts->addIndex(['ledger', 'code'], 'idx_accounts_ledger_code');
		$accounts->addIndex(['timestamp_seconds', 'timestamp_nanos'], 'idx_accounts_timestamp');

		$transfers = $schema->createTable('ledgering_transfers');
		$transfers->addColumn('sequence', 'bigint', ['unsigned' => true, 'autoincrement' => true]);
		$transfers->addColumn('id', 'binary', ['length' => 16, 'fixed' => true]);
		$transfers->addColumn('debit_account_id', 'binary', ['length' => 16, 'fixed' => true]);
		$transfers->addColumn('credit_account_id', 'binary', ['length' => 16, 'fixed' => true]);
		$transfers->addColumn('amount', 'bigint', ['unsigned' => true]);
		$transfers->addColumn('pending_id', 'binary', ['length' => 16, 'fixed' => true]);
		$transfers->addColumn('ledger', 'integer', ['unsigned' => true]);
		$transfers->addColumn('code', 'integer', ['unsigned' => true]);
		$transfers->addColumn('flags', 'integer', ['unsigned' => true]);
		$transfers->addColumn('timeout_seconds', 'bigint', ['unsigned' => true]);
		$transfers->addColumn('external_id_primary', 'binary', ['length' => 16, 'fixed' => true]);
		$transfers->addColumn('external_id_secondary', 'binary', ['length' => 16, 'fixed' => true]);
		$transfers->addColumn('external_code_primary', 'integer', ['unsigned' => true]);
		$transfers->addColumn('timestamp_seconds', 'bigint', ['unsigned' => true]);
		$transfers->addColumn('timestamp_nanos', 'integer', ['unsigned' => true]);
		$transfers->setPrimaryKey(['sequence']);
		$transfers->addUniqueIndex(['id'], 'uniq_transfers_id');
		$transfers->addIndex(['debit_account_id'], 'idx_transfers_debit_account');
		$transfers->addIndex(['credit_account_id'], 'idx_transfers_credit_account');
		$transfers->addIndex(['pending_id'], 'idx_transfers_pending_id');
		$transfers->addIndex(['external_id_primary'], 'idx_transfers_external_id_primary');
		$transfers->addIndex(['external_id_secondary'], 'idx_transfers_external_id_secondary');
		$transfers->addIndex(['ledger', 'code'], 'idx_transfers_ledger_code');
		$transfers->addIndex(['timestamp_seconds', 'timestamp_nanos'], 'idx_transfers_timestamp');

		$balances = $schema->createTable('ledgering_account_balances');
		$balances->addColumn('sequence', 'bigint', ['unsigned' => true, 'autoincrement' => true]);
		$balances->addColumn('account_id', 'binary', ['length' => 16, 'fixed' => true]);
		$balances->addColumn('debits_posted', 'bigint', ['unsigned' => true]);
		$balances->addColumn('credits_posted', 'bigint', ['unsigned' => true]);
		$balances->addColumn('debits_pending', 'bigint', ['unsigned' => true]);
		$balances->addColumn('credits_pending', 'bigint', ['unsigned' => true]);
		$balances->addColumn('timestamp_seconds', 'bigint', ['unsigned' => true]);
		$balances->addColumn('timestamp_nanos', 'integer', ['unsigned' => true]);
		$balances->setPrimaryKey(['sequence']);
		$balances->addIndex(['account_id', 'timestamp_seconds', 'timestamp_nanos'], 'idx_balances_account_timestamp');
	}

	/**
	 * Drop the ledgering schema.
	 *
	 * Removes all tables created by the up() method.
	 */
	public static function down(Schema $schema): void
	{
		$schema->dropTable('ledgering_account_balances');
		$schema->dropTable('ledgering_transfers');
		$schema->dropTable('ledgering_accounts');
	}
}
