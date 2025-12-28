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

namespace Castor\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration for the Ledgering bundle.
 */
final class Configuration implements ConfigurationInterface
{
	public function getConfigTreeBuilder(): TreeBuilder
	{
		$treeBuilder = new TreeBuilder('castor_ledgering');
		$rootNode = $treeBuilder->getRootNode();

		$rootNode
			->children()
				->arrayNode('dbal')
					->addDefaultsIfNotSet()
					->children()
						->scalarNode('connection_name')
							->info('The name of the Doctrine DBAL connection to use')
							->defaultNull()
						->end()
					->end()
				->end()
			->end();

		return $treeBuilder;
	}
}

