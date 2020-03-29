<?php

namespace Kickin\ExceptionHandlerBundle\Configuration;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Interface ConfigurationInterface
 *
 * @internal
 */
interface ConfigurationInterface
{
  /**
   * Indicate whether the current environment is a production environment
   *
   * @return bool
   */
  public function isProductionEnvironment(): bool;

  /**
   * Return the backtrace file root folder path
   *
   * @return string
   */
  public function getBacktraceFolder(): string;

  /**
   * Retrieve user information from the token, and return it in a single string
   *
   * @param TokenInterface|null $token
   *
   * @return string
   */
  public function getUserInformation(TokenInterface $token = NULL): string;

  /**
   * Retrieve the system version
   *
   * @return string
   */
  public function getSystemVersion(): string;

  /**
   * Representation of the error sender
   */
  public function getSender();

  /**
   * Representation of the error receiver
   */
  public function getReceiver();
}
