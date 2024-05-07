<?php

namespace Kickin\ExceptionHandlerBundle\Configuration;

interface SwiftMailerConfigurationInterface extends ConfigurationInterface
{
  /**
   * @return string|array
   */
  public function getSender();

  /**
   * @return mixed
   */
  public function getReceiver();
}
