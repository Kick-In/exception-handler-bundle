<?php

namespace Kickin\ExceptionHandlerBundle\Configuration;

/**
 * Interface SwiftMailerConfigurationInterface
 */
interface SwiftMailerConfigurationInterface extends ConfigurationInterface
{
  /**
   * @inheritDoc
   *
   * @return string|array
   */
  public function getSender();

  /**
   * @inheritDoc
   *
   * @return mixed
   */
  public function getReceiver();
}
