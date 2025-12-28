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

namespace Castor\Symfony\Migrations;

use Castor\Ledgering\Storage\Dbal\Migrator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the ledgering schema.
 *
 * This migration creates three tables:
 * - ledgering_accounts: Stores account information and current balances
 * - ledgering_transfers: Stores all transfers between accounts
 * - ledgering_account_balances: Stores historical balance snapshots (for accounts with HISTORY flag)
 */
final class Version20251227000000 extends AbstractMigration
{
	public function getDescription(): string
	{
		return 'Creates the ledgering schema (ledgering_accounts, ledgering_transfers, ledgering_account_balances)';
	}

	public function up(Schema $schema): void
	{
		Migrator::up($schema);
	}

	public function down(Schema $schema): void
	{
		Migrator::down($schema);
	}
}

