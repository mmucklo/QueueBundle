<?php

namespace Dtc\QueueBundle\DependencyInjection;

use Dtc\QueueBundle\Model\PriorityJobManager;
use Gedmo\Mapping\Annotation\Tree;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree.
     *
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('dtc_queue');

        $rootNode
            ->children()
                ->scalarNode('document_manager')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('entity_manager')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('default_manager')
                    ->defaultValue('odm')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('run_manager')
                ->end()
                ->scalarNode('job_timing_manager')
                ->end()
                ->booleanNode('record_timings')
                    ->defaultFalse()
                ->end()
                ->floatNode('record_timings_timezone_offset')
                    ->defaultValue(0)
                ->end()
                ->arrayNode('beanstalkd')
                    ->children()
                        ->scalarNode('host')->end()
                        ->scalarNode('tube')->end()
                    ->end()
                ->end()
                ->append($this->addRabbitMq())
                ->append($this->addAdmin())
                ->append($this->addClasses())
                ->append($this->addPriority())
            ->end();

        return $treeBuilder;
    }

    protected function addRetry() {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('retry');
        $rootNode
            ->children()
                ->arrayNode('max')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('retry')
                            ->defaultValue(3)
                        ->end()
                        ->integerNode('failure')
                            ->defaultValue(2)
                        ->end()
                        ->integerNode('exception')
                            ->defaultValue(1)
                        ->end()
                        ->integerNode('stalled')
                            ->defaultValue(2)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('auto')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('failure')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('exception')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
            ->end();
        return $rootNode;
    }
    
    protected function addPriority() {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('priority');
        $rootNode
            ->children()
                ->integerNode('max')
                    ->defaultValue(255)
                    ->min(1)
                ->end()
                ->enumNode('direction')
                    ->values([PriorityJobManager::PRIORITY_ASC, PriorityJobManager::PRIORITY_DESC])
                    ->defaultValue(PriorityJobManager::PRIORITY_DESC)
                ->end()
            ->end();
        return $rootNode;
    }

    protected function addClasses() {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('class');
        $rootNode
            ->children()
                ->scalarNode('job')->end()
                ->scalarNode('job_archive')->end()
                ->scalarNode('job_timing')->end()
                ->scalarNode('run')->end()
                ->scalarNode('run_archive')->end()
            ->end();
        return $rootNode;
    }

    protected function addAdmin()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('admin');
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('chartjs')->defaultValue('https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.1/Chart.bundle.min.js')->end()
            ->end();

        return $rootNode;
    }

    protected function addRedis() {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('redis');
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('snc_redis')
                    ->children()
                        ->enumNode('type')
                          ->values(['predis','phpredis'])
                            ->defaultNull()->end()
                        ->scalarNode('alias')
                            ->defaultNull()->end()
                    ->end()
                    ->validate()->ifTrue(function ($node) {
                        if (isset($node['type']) && !isset($node['alias'])) {
                            return false;
                        }
                        if (isset($node['alias']) && !isset($node['type'])) {
                            return false;
                        }
                        return true;
                    })->thenInvalid('if alias or type is set, then both must be set')->end()
                ->end()
                ->arrayNode('predis')
                    ->children()
                        ->scalarNode('dsn')->defaultNull()->end()
                        ->append($this->addPredisArgs())
                    ->end()
                ->end()
            ->end();
    }

    protected function addPredisArgs() {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('connection_parameters');
        $rootNode
            ->children()
                ->scalarNode('scheme')->defaultValue('tcp')->end()
                ->scalarNode('host')->defaultValue('127.0.0.1')->end()
                ->integerNode('port')->defaultValue(6379)->end()
                ->scalarNode('path')->defaultNull()->end()
                ->scalarNode('database')->defaultNull()->end()
                ->scalarNode('password')->defaultNull()->end()
                ->booleanNode('async')->defaultFalse()->end()
                ->booleanNode('persistent')->defaultFalse()->end()
                ->floatNode('timeout')->defaultValue(5.0)->end()
                ->floatNode('read_write_timeout')->defaultNull()->end()
                ->scalarNode('alias')->defaultNull()->end()
                ->integerNode('weight')->defaultNull()->end()
                ->booleanNode('iterable_multibulk')->defaultFalse()->end()
                ->booleanNode('throw_errors')->defaultTrue()->end()
            ->end();
    }

    protected function addRabbitMqOptions()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('options');
        $rootNode
            ->children()
                ->scalarNode('insist')->end()
                ->scalarNode('login_method')->end()
                ->scalarNode('login_response')->end()
                ->scalarNode('locale')->end()
                ->floatNode('connection_timeout')->end()
                ->floatNode('read_write_timeout')->end()
                ->booleanNode('keepalive')->end()
                ->integerNode('heartbeat')->end()
            ->end();

        return $rootNode;
    }

    protected function addRabbitMqSslOptions()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('ssl_options');
        $rootNode
            ->prototype('variable')->end()
            ->validate()
                ->ifTrue(function ($node) {
                    if (!is_array($node)) {
                        return true;
                    }
                    foreach ($node as $key => $value) {
                        if (is_array($value)) {
                            if ('peer_fingerprint' !== $key) {
                                return true;
                            } else {
                                foreach ($value as $key1 => $value1) {
                                    if (!is_string($key1) || !is_string($value1)) {
                                        return true;
                                    }
                                }
                            }
                        }
                    }

                    return false;
                })
                ->thenInvalid('Must be key-value pairs')
            ->end();

        return $rootNode;
    }

    protected function addRabbitMqExchange()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('exchange_args');
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('exchange')->defaultValue('dtc_queue_exchange')->end()
                ->booleanNode('type')->defaultValue('direct')->end()
                ->booleanNode('passive')->defaultFalse()->end()
                ->booleanNode('durable')->defaultTrue()->end()
                ->booleanNode('auto_delete')->defaultFalse()->end()
            ->end();

        return $rootNode;
    }

    protected function addRabbitMqArgs()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('queue_args');
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('queue')->defaultValue('dtc_queue')->end()
                ->booleanNode('passive')->defaultFalse()->end()
                ->booleanNode('durable')->defaultTrue()->end()
                ->booleanNode('exclusive')->defaultFalse()->end()
                ->booleanNode('auto_delete')->defaultFalse()->end()
            ->end();

        return $rootNode;
    }

    protected function addRabbitMq()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('rabbit_mq');
        $rootNode
            ->children()
                ->scalarNode('host')->end()
                ->scalarNode('port')->end()
                ->scalarNode('user')->end()
                ->scalarNode('password')->end()
                ->scalarNode('vhost')->defaultValue('/')->end()
                ->booleanNode('ssl')->defaultFalse()->end()
                ->append($this->addRabbitMqOptions())
                ->append($this->addRabbitMqSslOptions())
                ->append($this->addRabbitMqArgs())
                ->append($this->addRabbitMqExchange())
            ->end()
            ->validate()->always(function ($node) {
                if (empty($node['ssl_options'])) {
                    unset($node['ssl_options']);
                }
                if (empty($node['options'])) {
                    unset($node['options']);
                }

                return $node;
            })->end()
           ->validate()->ifTrue(function ($node) {
                if (isset($node['ssl_options']) && !$node['ssl']) {
                    return true;
                }

                return false;
            })->thenInvalid('ssl must be true in order to set ssl_options')->end()
        ->end();
        return $rootNode;
    }
}
