<?php

namespace Kickin\ExceptionHandlerBundle\Handler;

use Kickin\ExceptionHandlerBundle\Backtrace\BacktraceLogFile;
use Kickin\ExceptionHandlerBundle\Configuration\SwiftMailerConfigurationInterface;
use Swift_Attachment;
use Swift_FileSpool;
use Swift_Mailer;
use Swift_Transport;
use Swift_Transport_SpoolTransport;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Throwable;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class SwiftMailerExceptionHandler extends AbstractExceptionHandler implements EventSubscriberInterface
{
  /**
   * @var Swift_Mailer
   */
  private $mailer;

  /**
   * @var Swift_Transport
   */
  private $mailerTransport;

  /**
   * @var Environment
   */
  private $twig;

  /**
   * Constructor
   *
   * @param Swift_Mailer                      $mailer
   * @param Swift_Transport                   $transport
   * @param TokenStorageInterface             $tokenStorage
   * @param Environment                       $twig
   * @param SwiftMailerConfigurationInterface $configuration
   */
  public function __construct(
      Swift_Mailer $mailer, Swift_Transport $transport, Environment $twig,
      TokenStorageInterface $tokenStorage, SwiftMailerConfigurationInterface $configuration)
  {
    parent::__construct($tokenStorage, $configuration);

    $this->mailer          = $mailer;
    $this->mailerTransport = $transport;
    $this->twig            = $twig;
  }

  static public function getSubscribedEvents(): array
  {
    return parent::getSubscribedEvents();
  }

  /**
   * @throws LoaderError
   * @throws RuntimeError
   * @throws SyntaxError
   */
  protected function sendExceptionHandledFailedMessage(Throwable $uploadException, BacktraceLogFile $backtrace): void
  {
    // Build email
    $message = $this->mailer->createMessage()
        ->setSubject("Exception Handler: Failed to upload backtrace")
        ->setFrom($this->configuration->getSender())
        ->setTo($this->configuration->getReceiver())
        ->setBody($this->twig->render('@KickinExceptionHandler/Message/upload-failed.txt.twig', [
            'type'      => $uploadException ? get_class($uploadException) : NULL,
            'exception' => $uploadException->getMessage(),
            'backtrace' => $backtrace->getFileContent(),
        ]));

    // Send email
    $this->mailer->send($message);
  }

  /**
   * @throws LoaderError
   * @throws RuntimeError
   * @throws SyntaxError
   */
  protected function sendExceptionMessage(
      ?SessionInterface $session, string $requestUri, string $baseUrl, string $method,
      string $requestString, array $responseString, string $backtrace,
      string $serverVariablesString, string $globalVariablesString, string $extension): void
  {
    // Build attachments
    $serverVariableAttachment  = new Swift_Attachment($serverVariablesString, "server variables.txt", 'text/plain');
    $backtraceAttachment       = new Swift_Attachment($backtrace, "backtrace.txt", 'text/plain');
    $requestAttachment         = new Swift_Attachment($requestString, "request.txt", 'text/plain');
    $responseAttachment        = new Swift_Attachment($responseString[0], "response.txt", 'text/plain');
    $globalVariablesAttachment = new Swift_Attachment($globalVariablesString, "global variables.txt", 'text/plain');

    // Create the error mail
    $message = $this->mailer->createMessage()
        ->setSubject("Exception Handler: 500 error at " . $requestUri)
        ->setFrom($this->configuration->getSender())
        ->setTo($this->configuration->getReceiver())
        ->setBody($this->twig->render('@KickinExceptionHandler/Message/exception.txt.twig', [
            'user'          => $this->configuration->getUserInformation($this->tokenStorage->getToken()),
            'method'        => $method,
            'baseUrl'       => $baseUrl,
            'requestUri'    => $requestUri,
            'systemVersion' => $this->configuration->getSystemVersion(),
            'errorMessage'  => $session ? $session->get(self::SESSION_ERROR) : '',
            'extra'         => $extension,
        ]))
        ->attach($serverVariableAttachment)
        ->attach($backtraceAttachment)
        ->attach($requestAttachment)
        ->attach($responseAttachment)
        ->attach($globalVariablesAttachment);

    // Send email
    $this->mailer->send($message);
    $this->sendMessage();
  }

  /**
   * Flushes the Swift Mailer transport queue
   */
  private function sendMessage()
  {
    $transport = $this->mailer->getTransport();
    if ($transport instanceof Swift_Transport_SpoolTransport) {
      $spool = $transport->getSpool();
      if ($spool instanceof Swift_FileSpool) {
        $spool->recover();
      }
      $spool->flushQueue($this->mailerTransport);
    }
  }

}
