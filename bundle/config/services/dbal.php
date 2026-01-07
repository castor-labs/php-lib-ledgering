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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Castor\Ledgering\Storage\AccountBalanceReader;
use Castor\Ledgering\Storage\AccountReader;
use Castor\Ledgering\Storage\Dbal\AccountBalanceRepository;
use Castor\Ledgering\Storage\Dbal\AccountRepository;
use Castor\Ledgering\Storage\Dbal\TransactionalLedger;
use Castor\Ledgering\Storage\Dbal\TransferRepository;
use Castor\Ledgering\Storage\TransferReader;

return static function (ContainerConfigurator $container): void {
	$services = $container->services();

	// Account Repository
	$services->set('castor.ledgering.dbal.account_repository', AccountRepository::class)
		->args([service('castor.ledgering.dbal.connection')]);

	$services->alias(AccountReader::class, 'castor.ledgering.dbal.account_repository');

	// Transfer Repository
	$services->set('castor.ledgering.dbal.transfer_repository', TransferRepository::class)
		->args([service('castor.ledgering.dbal.connection')]);

	$services->alias(TransferReader::class, 'castor.ledgering.dbal.transfer_repository');

	// Account Balance Repository
	$services->set('castor.ledgering.dbal.account_balance_repository', AccountBalanceRepository::class)
		->args([service('castor.ledgering.dbal.connection')]);

	$services->alias(AccountBalanceReader::class, 'castor.ledgering.dbal.account_balance_repository');

	// Transactional Ledger (decorates the standard ledger)
	$services->set('castor.ledgering.dbal.transactional_ledger', TransactionalLedger::class)
		->args([
			service('castor.ledgering.dbal.connection'),
			service('castor.ledgering.ledger'),
		]);
};

