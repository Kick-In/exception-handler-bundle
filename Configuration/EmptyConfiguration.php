<?php

namespace Kickin\ExceptionHandlerBundle\Configuration;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class EmptyConfiguration implements ConfigurationInterface
{

  /**
   * @var ContainerInterface
   */
  private $container;

  /**
   * EmptyConfiguration constructor.
   *
   * @param ContainerInterface $container
   */
  public function __construct(ContainerInterface $container)
  {
    $this->container = $container;
  }

  /**
   * @inheritdoc
   */
  public function isProductionEnvironment()
  {
    return false;
  }

  /**
   * @inheritdoc
   */
  public function getBacktraceFolder()
  {
    return $this->container->getParameter('kernel.cache_dir' . '/exceptionhandler');
  }

  /**
   * SwiftMailer representation of the error sender
   *
   * @return string|array
   */
  public function getSender()
  {
    return array('Exception Handler' => 'no-reply@domain.com');
  }

  /**
   * SwiftMailer representation of the error receiver
   *
   * @return mixed
   */
  public function getReceiver()
  {
    return array('receiver' => 'example@example.net');
  }

  /**
   * Retrieve user information from the token, and return it in a single string
   *
   * @param TokenInterface $token
   *
   * @return string
   */
  public function getUserInformation(TokenInterface $token)
  {
    if ($token !== NULL) {
      return $token->getUsername();
    }

    return 'No user';
  }

  /**
   * Retrieve the system version
   *
   * @return mixed
   */
  public function getSystemVersion()
  {
    return 'git-hash';
  }
}
