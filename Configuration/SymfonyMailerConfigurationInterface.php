<?php

namespace Kickin\ExceptionHandlerBundle\Configuration;

use Symfony\Component\Mime\Address;

interface SymfonyMailerConfigurationInterface extends ConfigurationInterface
{
  /**
   * @return Address|string
   */
  public function getSender();

  /**
   * @return Address|string
   */
  public function getReceiver();
}
