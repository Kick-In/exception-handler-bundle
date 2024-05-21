<?php

namespace Kickin\ExceptionHandlerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
  public function getConfigTreeBuilder(): TreeBuilder
  {
    $treeBuilder = new TreeBuilder('kickin_exception_handler');

    $rootNode = $treeBuilder->getRootNode();

    // Here you should define the parameters that are allowed to
    // configure your bundle. See the documentation linked above for
    // more information on that topic.

    $rootNode
        ->children()
          ->enumNode('mail_backend')
            ->values(['swift', 'swift_mailer', 'symfony', 'symfony_mailer'])
            ->defaultValue('swift')
          ->end() // mailer_style
        ->end(); // root children

    return $treeBuilder;
  }
}
