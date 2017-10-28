<?php

namespace KickIn\ExceptionHandlerBundle\Configuration;

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
   * @param TokenInterface $token
   *
   * @return string
   */
  public function getUserInformation(TokenInterface $token);

  /**
   * Retrieve the system version
   *
   * @return mixed
   */
  public function getSystemVersion();
}