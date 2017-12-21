<?php

namespace Kickin\ExceptionHandlerBundle\Configuration;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

interface ConfigurationInterface
{
  /**
   * ConfigurationInterface constructor.
   *
   * @param ContainerInterface $container
   */
  public function __construct(ContainerInterface $container);

  /**
   * Indicate whether the current environment is a production environment
   *
   * @return bool
   */
  public function isProductionEnvironment();

  /**
   * Return the backtrace file root folder path
   *
   * @return string
   */
  public function getBacktraceFolder();

  /**
   * SwiftMailer representation of the error sender
   *
   * @return string|array
   */
  public function getSender();

  /**
   * SwiftMailer representation of the error receiver
   *
   * @return mixed
   */
  public function getReceiver();

  /**
   * Retrieve user information from the token, and return it in a single string
   *
   * @param TokenInterface|null $token
   *
   * @return string
   */
  public function getUserInformation(TokenInterface $token = null);

  /**
   * Retrieve the system version
   *
   * @return mixed
   */
  public function getSystemVersion();
}
