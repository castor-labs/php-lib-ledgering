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

namespace Castor\Symfony;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Symfony bundle for Castor Ledgering.
 *
 * Provides integration with Symfony framework for the ledgering library.
 */
final class LedgeringBundle extends AbstractBundle implements PrependExtensionInterface
{
	protected string $extensionAlias = 'castor_ledgering';

	public function configure(DefinitionConfigurator $definition): void
	{
        $definition->import(__DIR__.'/../config/definition.php');
	}

	public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
	{
		$connectionName = $config['dbal']['connection_name'] ?? 'doctrine.dbal.default_connection';
		$idempotency = $config['idempotency'] ?? true;

        $container->services()->alias('castor.ledgering.dbal.connection_name', $connectionName);

		$container->import('../config/services/core.php');
		$container->import('../config/services/dbal.php');
		$container->import('../config/services/commands.php');

		if ($idempotency) {
			$container->import('../config/services/idempotency.php');
		}
	}

	public function prepend(ContainerBuilder $container): void
	{
		$migrationsPath = \dirname(__DIR__) . '/config/migrations';

		$container->prependExtensionConfig('doctrine_migrations', [
			'migrations_paths' => [
				'Castor\\Symfony\\Migrations' => $migrationsPath,
			],
		]);
	}
}

