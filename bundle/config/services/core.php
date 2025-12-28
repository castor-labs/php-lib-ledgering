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

use Castor\Ledgering\Ledger;
use Castor\Ledgering\StandardLedger;
use Castor\Ledgering\Time\Clock;
use Castor\Ledgering\Time\DefaultClock;

return static function (ContainerConfigurator $container): void {
	$services = $container->services();

	// Clock service
	$services->set('castor.ledgering.clock', DefaultClock::class);

	$services->alias(Clock::class, 'castor.ledgering.clock');

	// Standard Ledger (decorated by TransactionalLedger in dbal.php)
	$services->set('castor.ledgering.ledger', StandardLedger::class)
		->args([
			service('castor.ledgering.dbal.account_repository'),
			service('castor.ledgering.dbal.transfer_repository'),
			service('castor.ledgering.dbal.account_balance_repository'),
			service('castor.ledgering.clock'),
		]);

	// Alias for the Ledger interface - points to the transactional decorator
	$services->alias(Ledger::class, 'castor.ledgering.dbal.transactional_ledger');
};

