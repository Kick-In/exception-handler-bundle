<?php

namespace Kickin\ExceptionHandlerBundle\DependencyInjection;

use Exception;
use Kickin\ExceptionHandlerBundle\Handler\SwiftMailerExceptionHandler;
use Kickin\ExceptionHandlerBundle\Handler\SymfonyMailerExceptionHandler;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class KickinExceptionHandlerExtension extends Extension
{
  public function load(array $configs, ContainerBuilder $container): void
  {
    // Parse configuration
    $configuration = new Configuration();
    $config        = $this->processConfiguration($configuration, $configs);

    // Configure the correct service
    if (in_array($config['mail_backend'], ['swift', 'swift_mailer'])) {
      $this->configureSwiftmailer($container);
    } else {
      $this->configureSymfonyMailer($container);
    }
  }

  /**
   * Configures the Swift Mailer handler
   */
  private function configureSwiftMailer(ContainerBuilder $container): void
  {
    if (!class_exists('Swift_Mailer')) {
      throw new InvalidConfigurationException('You selected SwiftMailer as mail backend, but it is not installed. Try running `composer req symfony/swiftmailer-bundle` or switch to the Symfony mailer in the configuration.');
    }

    $this->configureMailer($container, SwiftMailerExceptionHandler::class)
        // Explicitly bind the correct mailer, as we do not want the default mailer
        ->setArgument('$mailer', new Reference('swiftmailer.mailer.exception_mailer'))
        // Explicitly bind the correct real transport, as we do not want the default mailer transport
        // Next to that, we also need the real transport, and not the spool
        ->setArgument('$transport', new Reference('swiftmailer.mailer.exception_mailer.transport.real'));
  }

  /**
   * Configures the Symfony Mailer handler
   */
  private function configureSymfonyMailer(ContainerBuilder $container): void
  {
    if (!class_exists('Symfony\Component\Mailer\Mailer')) {
      throw new InvalidConfigurationException('You selected the Symfony mailer as mail backend, but it is not installed. Try running `composer req symfony/mailer` or switch to SwiftMailer in the configuration.');
    }

    $this->configureMailer($container, SymfonyMailerExceptionHandler::class);
  }

  /**
   * Configure the shared defaults for both handlers
   */
  private function configureMailer(ContainerBuilder $container, string $handlerClass): Definition
  {
    return $container
        ->autowire($handlerClass)
        ->setPublic(false)
        ->setLazy(true)
        ->setAutoconfigured(true);
  }
}
