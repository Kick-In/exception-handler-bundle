```php
<?php

namespace Kickin\ExceptionHandlerBundle\Configuration;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class ExampleConfiguration implements ConfigurationInterface
{
  /**
   * @var string
   */
  private $cacheDir;

  /**
   * EmptyConfiguration constructor.
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
  public function isProductionEnvironment()
  {
    return false;
  }

  /**
   * @inheritdoc
   */
  public function getBacktraceFolder()
  {
    return $this->cacheDir . '/exception-handler';
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
   * @param TokenInterface|null $token
   *
   * @return string
   */
  public function getUserInformation(TokenInterface $token = null)
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

```