```php
<?php

namespace Kickin\ExceptionHandlerBundle\Configuration;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class ExampleConfiguration implements SwiftMailerConfigurationInterface
{
  /**
   * @var string
   */
  private $cacheDir;

  /**
   * ExampleConfiguration constructor.
   *
   * @param string $cacheDir
   */
  public function __construct($cacheDir)
  {
    $this->cacheDir = $cacheDir;
  }

  /**
   * @inheritdoc
   */
  public function isProductionEnvironment(): bool
  {
    return false;
  }

  /**
   * @inheritdoc
   */
  public function getBacktraceFolder(): bool
  {
    return $this->cacheDir . '/exception-handler';
  }

  /**
   * @inheritdoc
   */
  public function getSender()
  {
    return array('Exception Handler' => 'no-reply@domain.com');
  }

  /**
   * @inheritdoc
   */
  public function getReceiver()
  {
    return array('receiver' => 'example@example.net');
  }

  /**
   * @inheritdoc
   */
  public function getUserInformation(TokenInterface $token = null): string
  {
    if ($token !== NULL) {
      return $token->getUsername();
    }

    return 'No user';
  }

  /**
   * @inheritdoc
   */
  public function getSystemVersion(): string
  {
    return 'git-hash';
  }
}

```
