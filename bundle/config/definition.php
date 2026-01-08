<?php

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;

return static function (DefinitionConfigurator $definition): void {
    $definition->rootNode()
        ->children()
            ->booleanNode('idempotency')
                ->info('Enable idempotent ledger decorator (suppresses duplicate creation errors)')
                ->defaultTrue()
            ->end()
            ->arrayNode('dbal')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('connection_name')
                        ->info('The name of the Doctrine DBAL connection to use')
                        ->defaultNull()
                    ->end()
                ->end()
            ->end()
        ->end()
    ;
};