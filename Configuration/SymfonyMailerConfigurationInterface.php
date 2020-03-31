<?php

namespace Kickin\ExceptionHandlerBundle\Configuration;

use Symfony\Component\Mime\Address;

/**
 * Interface SymfonyMailerConfigurationInterface
 */
interface SymfonyMailerConfigurationInterface extends ConfigurationInterface
{
  /**
   * @inheritDoc
   *
   * @return Address|string
   */
  public function getSender();

  /**
   * @inheritDoc
   *
   * @return Address|string
   */
  public function getReceiver();
}
