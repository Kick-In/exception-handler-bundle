<?php

namespace Kickin\ExceptionHandlerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
  /**
   * {@inheritDoc}
   */
  public function getConfigTreeBuilder()
  {
    $treeBuilder = new TreeBuilder('kickin_exception_handler');

    if (method_exists($treeBuilder, 'getRootNode')) {
      $rootNode = $treeBuilder->getRootNode();
    } else {
      // for symfony/config 4.1 and older
      $rootNode = $treeBuilder->root('kickin_exception_handler');
    }

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
