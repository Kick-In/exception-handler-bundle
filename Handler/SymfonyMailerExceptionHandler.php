<?php

namespace Kickin\ExceptionHandlerBundle\Handler;

use Kickin\ExceptionHandlerBundle\Backtrace\BacktraceLogFile;
use Kickin\ExceptionHandlerBundle\Configuration\SymfonyMailerConfigurationInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Class MailerExceptionHandler
 */
class SymfonyMailerExceptionHandler extends AbstractExceptionHandler implements EventSubscriberInterface
{
  /**
   * @var MailerInterface
   */
  private $mailer;

  /**
   * Constructor
   *
   * @param MailerInterface                     $mailer
   * @param TokenStorageInterface               $tokenStorage
   * @param SymfonyMailerConfigurationInterface $configuration
   */
  public function __construct(
      MailerInterface $mailer, TokenStorageInterface $tokenStorage, SymfonyMailerConfigurationInterface $configuration)
  {
    parent::__construct($tokenStorage, $configuration);

    $this->mailer = $mailer;
  }

  /**
   * @inheritdoc
   */
  static public function getSubscribedEvents()
  {
    return parent::getSubscribedEvents();
  }

  /**
   * @inheritDoc
   * @throws TransportExceptionInterface
   */
  protected function sendExceptionHandledFailedMessage($uploadException, BacktraceLogFile $backtrace): void
  {
    // Build email
    $email = (new TemplatedEmail())
        ->subject("Exception Handler: Failed to upload backtrace")
        ->from($this->configuration->getSender())
        ->to($this->configuration->getReceiver())
        ->textTemplate('@KickinExceptionHandler/Message/upload-failed.txt.twig')
        ->context([
            'type'      => $uploadException ? get_class($uploadException) : NULL,
            'exception' => $uploadException->getMessage(),
            'backtrace' => $backtrace->getFileContent(),
        ]);

    // Send email
    $this->mailer->send($email);
  }

  /**
   * @inheritDoc
   * @throws TransportExceptionInterface
   */
  protected function sendExceptionMessage(
      ?SessionInterface $session, string $requestUri, string $baseUrl, string $method,
      string $requestString, array $responseString, string $backtrace,
      string $serverVariablesString, string $globalVariablesString, string $extension): void
  {
    // Create the error mail
    $email = (new TemplatedEmail())
        ->subject(sprintf('Exception Handler: 500 error at %s', $requestUri))
        ->from($this->configuration->getSender())
        ->to($this->configuration->getReceiver())
        ->textTemplate('@KickinExceptionHandler/Message/exception.txt.twig')
        ->context([
            'user'          => $this->configuration->getUserInformation($this->tokenStorage->getToken()),
            'method'        => $method,
            'baseUrl'       => $baseUrl,
            'requestUri'    => $requestUri,
            'systemVersion' => $this->configuration->getSystemVersion(),
            'errorMessage'  => $session ? $session->get(self::SESSION_ERROR) : '',
            'extra'         => $extension ?? '',
        ])
        ->attach($serverVariablesString, "server variables.txt", 'text/plain')
        ->attach($backtrace, "backtrace.txt", 'text/plain')
        ->attach($requestString, "request.txt", 'text/plain')
        ->attach($responseString[0], "response.txt", 'text/plain')
        ->attach($globalVariablesString, "global variables.txt", 'text/plain');

    // Send email
    $this->mailer->send($email);
  }
}
