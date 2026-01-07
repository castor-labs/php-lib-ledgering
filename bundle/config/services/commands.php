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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Castor\Symfony\Command\ExpirePendingTransfers;

return static function (ContainerConfigurator $container): void {
	$services = $container->services();

	// Console Commands
	$services->set('castor.ledgering.command.expire_pending_transfers', ExpirePendingTransfers::class)
		->args([
			service('castor.ledgering.dbal.transactional_ledger'),
		])
		->tag('console.command');
};

