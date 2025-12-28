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

namespace Castor\Ledgering\Infra;

use Castor\Ledgering\Storage\Dbal\Migrator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;

/**
 * Test database infrastructure.
 *
 * Provides a singleton database connection for integration tests.
 * The database is initialized once and reused across all tests.
 */
final class Database
{
	private static ?Connection $connection = null;

	private static bool $initialized = false;

	/**
	 * Get the database connection.
	 *
	 * Creates the connection on first call using the TEST_DATABASE_URI environment variable.
	 *
	 * @throws \RuntimeException if TEST_DATABASE_URI is not set
	 */
	public static function connection(): Connection
	{
		if (self::$connection === null) {
			$uri = $_ENV['TEST_DATABASE_URI'] ?? $_SERVER['TEST_DATABASE_URI'] ?? \getenv('TEST_DATABASE_URI');

			if ($uri === false || $uri === null || $uri === '') {
				throw new \RuntimeException(
					'TEST_DATABASE_URI environment variable is not set. '.
					'Please set it to a valid database connection string (e.g., pgsql://user:pass@localhost/test_db)',
				);
			}

			// Use DBAL's DSN parser to parse the connection URL
			// Map common URL schemes to DBAL driver names
			$dsnParser = new DsnParser([
				'postgres' => 'pdo_pgsql',
				'postgresql' => 'pdo_pgsql',
				'pgsql' => 'pdo_pgsql',
				'mysql' => 'pdo_mysql',
				'mariadb' => 'pdo_mysql',
				'sqlite' => 'pdo_sqlite',
				'sqlite3' => 'pdo_sqlite',
			]);

			$params = $dsnParser->parse($uri);

			self::$connection = DriverManager::getConnection($params);
		}

		return self::$connection;
	}

	/**
	 * Initialize the database schema.
	 *
	 * This method is called once before running database tests.
	 * It drops and recreates all tables to ensure a clean state.
	 */
	public static function initialize(): void
	{
		if (self::$initialized) {
			return;
		}

		$connection = self::connection();
		$schemaManager = $connection->createSchemaManager();

		// Get current schema
		$currentSchema = $schemaManager->introspectSchema();

		// Create a new schema with our tables
		$newSchema = new Schema();
		Migrator::up($newSchema);

		// Drop existing tables if they exist
		if ($currentSchema->hasTable('ledgering_account_balances')) {
			$connection->executeStatement('DROP TABLE ledgering_account_balances');
		}
		if ($currentSchema->hasTable('ledgering_transfers')) {
			$connection->executeStatement('DROP TABLE ledgering_transfers');
		}
		if ($currentSchema->hasTable('ledgering_accounts')) {
			$connection->executeStatement('DROP TABLE ledgering_accounts');
		}

		// Create tables
		$queries = $newSchema->toSql($connection->getDatabasePlatform());
		foreach ($queries as $query) {
			$connection->executeStatement($query);
		}

		self::$initialized = true;
	}

	/**
	 * Reset the database to a clean state.
	 *
	 * Truncates all tables to remove data while keeping the schema intact.
	 * This is faster than dropping and recreating tables.
	 */
	public static function reset(): void
	{
		$connection = self::connection();
		$platform = $connection->getDatabasePlatform();

		if ($platform instanceof MySQLPlatform || $platform instanceof MariaDBPlatform) {
			$connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
			$connection->executeStatement('TRUNCATE TABLE ledgering_account_balances');
			$connection->executeStatement('TRUNCATE TABLE ledgering_transfers');
			$connection->executeStatement('TRUNCATE TABLE ledgering_accounts');
			$connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
		} elseif ($platform instanceof PostgreSQLPlatform) {
			$connection->executeStatement('TRUNCATE TABLE ledgering_account_balances, ledgering_transfers, ledgering_accounts CASCADE');
		} else {
			// Fallback for other databases
			$connection->executeStatement('DELETE FROM ledgering_account_balances');
			$connection->executeStatement('DELETE FROM ledgering_transfers');
			$connection->executeStatement('DELETE FROM ledgering_accounts');
		}
	}

	/**
	 * Close the database connection.
	 *
	 * This is typically called after all tests have finished.
	 */
	public static function close(): void
	{
		if (self::$connection !== null) {
			self::$connection->close();
			self::$connection = null;
			self::$initialized = false;
		}
	}
}
