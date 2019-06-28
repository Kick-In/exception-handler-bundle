<?php

namespace Kickin\ExceptionHandlerBundle\Configuration;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\NamedAddress;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

interface ConfigurationInterface
{
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
   * Address representation of the error sender
   *
   * @return Address|string
   */
  public function getSender();

  /**
   * Address representation of the error receiver(s)
   *
   * @return Address|string
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
