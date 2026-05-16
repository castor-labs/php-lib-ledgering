<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Castor\Symfony\Command\ExpirePendingTransfers;

return static function (ContainerConfigurator $container): void {
	$services = $container->services();

	// Console Commands
	$services
		->set('castor.ledgering.command.expire_pending_transfers', ExpirePendingTransfers::class)
		->args([
			service('castor.ledgering.dbal.transactional_ledger'),
		])
		->tag('console.command');
};
