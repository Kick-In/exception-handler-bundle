<?php

namespace Kickin\ExceptionHandlerBundle\Handler;

use DateTime;
use Exception;
use Kickin\ExceptionHandlerBundle\Backtrace\BacktraceLogFile;
use Kickin\ExceptionHandlerBundle\Configuration\ConfigurationInterface;
use Kickin\ExceptionHandlerBundle\Configuration\EmptyConfiguration;
use Kickin\ExceptionHandlerBundle\Exceptions\FileAlreadyExistsException;
use Kickin\ExceptionHandlerBundle\Exceptions\UploadFailedException;
use Swift_Attachment;
use Swift_Mailer;
use Swift_Transport;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig_Environment;

/**
 * Description of ExceptionHandler
 * This Service triggers on the kernel.exception and catches any HTTP 500 error. When an HTTP 500 error is detected,
 * an e-mail is send to maintainers.
 *
 * @author Wendo
 * @author BobV
 * @author Sven
 */
class ExceptionHandler implements EventSubscriberInterface
{

  /**
   * Status constants to define the different state of the save process of a back trace file.
   */
  const FILE_SAVE_SUCCESS = 0;  // File successfully saved
  const FILE_SAVE_TRYING = 1;   // Trying to save file (again)
  const FILE_SAVE_FAILED = 2;   // Saving file failed.

  /**
   * Session constants identifying the session keys
   */
  const SESSION_FILENAME = "kickin.exceptionhandler.filename";
  const SESSION_ERROR = "kickin.exceptionhandler.error";
  const SESSION_PREVIOUS_TIME = "kickin.exceptionhandler.previous_time";
  const SESSION_PREVIOUS_HASH = "kickin.exceptionhandler.previous_hash";
  const EXCEPTION_PRESENCE = 'kickin.exceptionhandler.exception.present';

  /**
   * @var Swift_Mailer
   */
  private $mailer;

  /**
   * @var Swift_Transport
   */
  private $mailerTransport;

  /**
   * @var TokenStorageInterface
   */
  private $tokenStorage;

  /**
   * @var Twig_Environment
   */
  private $twig;

  /**
   * @var ConfigurationInterface
   */
  private $configuration;

  /**
   * Constructor
   *
   * @param Swift_Mailer           $mailer
   * @param Swift_Transport        $transport
   * @param TokenStorageInterface  $tokenStorage
   * @param Twig_Environment       $twig
   * @param ConfigurationInterface $configuration
   */
  public function __construct(Swift_Mailer $mailer, Swift_Transport $transport, TokenStorageInterface $tokenStorage,
                              Twig_Environment $twig, ConfigurationInterface $configuration)
  {
    if ($configuration instanceof EmptyConfiguration) {
      throw new \InvalidArgumentException("You need to create your own configuration class and register it correctly in order to use this bundle!");
    }

    $this->mailer          = $mailer;
    $this->mailerTransport = $transport;
    $this->tokenStorage    = $tokenStorage;
    $this->twig            = $twig;
    $this->configuration   = $configuration;
  }

  /**
   * @inheritdoc
   * @return array
   *
   * We need to handle both events: the KernelException event is used to catch any Exception event,
   * while the KernelResponse event is used to determine if the 500 error message is actually displayed to the user.
   * This ensures that NotAuthorizedExceptions are not mailed as a 500 error (as a 403 error is presented).
   */
  static public function getSubscribedEvents()
  {
    return array(
        KernelEvents::EXCEPTION => array(array('onKernelException', 0)),
        KernelEvents::RESPONSE  => array(array('onKernelResponse', 0)),
    );
  }

  /**
   * Handles a kernel exception by queueing it for sending
   *
   * @param GetResponseForExceptionEvent $event
   *
   * @throws \Twig_Error_Loader
   * @throws \Twig_Error_Runtime
   * @throws \Twig_Error_Syntax
   */
  public function onKernelException(GetResponseForExceptionEvent $event)
  {
    // Skip some exception types directly
    $exception = $event->getException();
    if ($exception instanceof NotFoundHttpException ||
        $exception instanceof AccessDeniedHttpException ||
        $exception instanceof BadRequestHttpException ||
        $exception instanceof GoneHttpException) {
      return;
    }

    $this->handleException($exception, $event->getRequest()->getSession());
  }

  /**
   * This function will create the backtrace and save it with an random
   * name in /web/uploads/exceptionBacktrace/ and save the name in the session so
   * in the html status exception handler load the backtrace and send it in an email.
   *
   * @param Exception        $exception
   * @param SessionInterface $session
   *
   * @throws \Twig_Error_Loader
   * @throws \Twig_Error_Runtime
   * @throws \Twig_Error_Syntax
   */
  public function handleException(Exception $exception, SessionInterface $session)
  {
    // If not production, return
    if (!$this->configuration->isProductionEnvironment()) return;

    // Create log file
    $backtrace = new BacktraceLogFile($this->configuration->getBacktraceFolder());
    $backtrace->setFileContent($this->buildBacktrace($exception));

    // Unset the used session variable
    if ($session) {
      $session->remove(self::SESSION_FILENAME);
      $session->set(self::SESSION_ERROR, $exception->getMessage());
    }

    /*
     * Now the backtrace has to be uploaded. This will be done using a status variable and can have 3 values:
     * "trying" -> in this case the system tries to upload the file. Normally it takes only 1 try but in
     *        case of a already used file name it could take some more tries (with different file names).
     * "upload fail" -> Upload went wrong so sent a mail with the backtrace and that something went wrong.
     * "ready" -> everything went well so it's OK.
     */
    $status          = self::FILE_SAVE_TRYING;
    $counter         = 0;
    $uploadException = NULL;

    while ($status === self::FILE_SAVE_TRYING) {

      if ($counter >= 3) {
        $status = self::FILE_SAVE_FAILED;
        break;
      }

      // Try to upload the backtrace
      try {
        $backtrace->uploadFile();
        $status = self::FILE_SAVE_SUCCESS;
      } catch (FileAlreadyExistsException $e) {
        // File already exists so give it a new name and retry
        $backtrace->generateNewName();
        $uploadException = $e;
      } catch (UploadFailedException $e) {
        // Just retry
        $uploadException = $e;
      } catch (Exception $e) {
        $status          = self::FILE_SAVE_FAILED;
        $uploadException = $e;
      }

      $counter++;
    }

    $uploadExceptionType = get_class($uploadException);

    if ($status === self::FILE_SAVE_SUCCESS) {
      // Uploading of the file succeeded
      // Backtrace has been uploaded -> put the address in a session variable
      $fileName = $backtrace->getName();
      if ($session) {
        $session->set(self::SESSION_FILENAME, $fileName);
        $session->set(self::EXCEPTION_PRESENCE, true);
      }
    } else {
      // Uploading went wrong -> just mail the backtrace so we have saved it.
      // Build email
      $message = $this->mailer->createMessage()
          ->setSubject("Exception Handler: Failed to upload backtrace")
          ->setFrom($this->configuration->getSender())
          ->setTo($this->configuration->getReceiver())
          ->setBody($this->twig->render('@KickinExceptionHandler/Message/upload-failed.txt.twig', array(
              'type'      => $uploadExceptionType,
              'exception' => $uploadException->getMessage(),
              'backtrace' => $backtrace->getFileContent(),
          )));

      // Send email
      $this->mailer->send($message);
    }
  }

  /**
   * This function builds a backtrace
   *
   * @param Exception $exception
   *
   * @return string
   */
  private function buildBacktrace(Exception $exception)
  {
    $user = $this->configuration->getUserInformation($this->tokenStorage->getToken());

    // Build backtrace
    $comment = "Exception Code: " . $exception->getCode();
    $comment .= "\nThe class of the exception: " . get_class($exception);
    $comment .= "\nThe user who triggered the exception: " . $user;
    $comment .= "\nFile in which the exceptionvent->getException() occured: " . $exception->getFile();
    $comment .= "\nException message: " . $exception->getMessage();
    $comment .= "\n\nThe backtrace:\n" . $exception->getTraceAsString();

    return $comment;
  }

  /**
   * This function handles the Kernel exception response. When the response contains an http 500 error, it is tried to
   * get the backtrace from the server (via the file name stored in the session) and mail this to the maintainers
   *
   * @author Wendo
   *
   * @param FilterResponseEvent $responseObject
   *
   * @throws \Twig_Error_Loader
   * @throws \Twig_Error_Runtime
   * @throws \Twig_Error_Syntax
   */
  public function onKernelResponse(FilterResponseEvent $responseObject)
  {
    $response = $responseObject->getResponse();
    $request  = $responseObject->getRequest();
    $session  = $request->getSession();

    //Check if there is actually an exception to be returned
    if (!$session->has(self::EXCEPTION_PRESENCE)) return;
    $session->remove(self::EXCEPTION_PRESENCE);

    //If we're not in production, remove the session file from FS and remove the filename from the session
    if (!$this->configuration->isProductionEnvironment()) {
      // no production! -> don't sent an email
      if (!$session->has(self::SESSION_FILENAME)) return;

      try {
        $fs = new Filesystem();
        $fs->remove($session->get(self::SESSION_FILENAME));
      } catch (Exception $e) {
        // Do nothing when this fails
      }
      $session->remove(self::SESSION_FILENAME);

      return;
    }

    // Get the routing and some other stuff out of the request
    $method     = $request->getMethod();
    $requestUri = $request->getRequestUri();
    $baseUrl    = $request->getHost();

    // Remove any personal stuff from the request variables
    $this->removeVar($request, 'request', 'password');
    $this->removeVar($request, 'request', '_password');
    $this->removeVar($request, 'server', 'PHP_AUTH_PW');
    $this->removeVar($request, 'server', 'HTTP_COOKIE');
    $this->removeVar($request, 'headers', 'php-auth-pw');
    $this->removeVar($request, 'headers', 'cookie');
    $this->removeVar($request, 'cookies', 'REMEMBERME');
    $this->removeVar($request, 'cookies', 'PHPSESSID');

    // Get the POST variables
    $responseString   = $response->__toString();
    $requestString    = $this->getRequestString($request);
    $responseContent  = $response->getContent();
    $responseString   = explode($responseContent, $responseString);
    $sessionVariables = $session->all();
    $cookieVariables  = $request->cookies->all();

    // Remove the security token out of the session
    unset($sessionVariables['_security_main']);

    // Make a string of the globals variables
    $globalVariablesString = "\nSession variables: ";
    $globalVariablesString .= "\n" . print_r($sessionVariables, true);
    $globalVariablesString .= "\n\nCookie variables: ";
    $globalVariablesString .= "\n" . print_r($cookieVariables, true);
    $serverVariablesString = "Server variables: ";
    $serverVariablesString .= "\n" . print_r($request->server, true);

    // Get backtrace out of session.
    $hash = $this->generateHash($requestUri, $session->get(self::SESSION_ERROR));
    $now  = new DateTime();
    if ($session->get(self::SESSION_PREVIOUS_TIME) !== NULL && $session->get(self::SESSION_PREVIOUS_HASH) == $hash) {
      $previousDatetime = DateTime::createFromFormat('Y-m-d H:i', $session->get(self::SESSION_PREVIOUS_TIME));
      $previousDatetime->modify('+10 minutes');
    } else {
      $previousDatetime = NULL;
    }

    // Check if error is repeated
    if (!$session->has(self::SESSION_FILENAME) ||
        ($session->get(self::SESSION_PREVIOUS_HASH, '') == $hash && $previousDatetime !== NULL && $previousDatetime > $now)
    ) {
      return;
    }

    $session->set(self::SESSION_PREVIOUS_HASH, $hash);
    $session->set(self::SESSION_PREVIOUS_TIME, $now->format('Y-m-d H:i'));

    // Retrieve backtrace contents
    $fs            = new Filesystem();
    $backtraceFile = $session->get(self::SESSION_FILENAME);
    $backtrace     = file_get_contents($backtraceFile);
    if (!$backtrace) {
      $backtrace = "FILE NOT FOUND. THE BACKTRACE FILE COULDN'T BE FOUND ON THE SERVER. TOO BAD... MAYBE HAVE A LOOK?\n\n File: $backtraceFile";
    }

    // Try to delete the file, when failed extend the mail body with a notification of this failure
    try {
      $fs->remove($backtraceFile);
    } catch (IOException $e) {
      $extension = "\n\n Oh and by the way, it failed to remove the backtrace file (name: " . $backtraceFile . "). Then you know that ;)\n\n";
    }

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
        ->setBody($this->twig->render('@KickinExceptionHandler/Message/exception.txt.twig', array(
            'user'          => $this->configuration->getUserInformation($this->tokenStorage->getToken()),
            'method'        => $method,
            'baseUrl'       => $baseUrl,
            'requestUri'    => $requestUri,
            'systemVersion' => $this->configuration->getSystemVersion(),
            'errorMessage'  => $session->get(self::SESSION_ERROR),
            'extra'         => isset($extension) ? $extension : '',
        )))
        ->attach($serverVariableAttachment)
        ->attach($backtraceAttachment)
        ->attach($requestAttachment)
        ->attach($responseAttachment)
        ->attach($globalVariablesAttachment);

    // Send email
    $this->mailer->send($message);
    $this->sendMessage();

    // Unset session
    $session->remove(self::SESSION_FILENAME);
    $session->remove(self::SESSION_ERROR);
  }

  /**
   * @param $url
   * @param $exceptionMessage
   *
   * @return string
   */
  private function generateHash($url, $exceptionMessage)
  {
    return md5($url . $exceptionMessage);
  }

  /**
   * Removes a variable from the request
   *
   * @param Request $request
   * @param string  $parameterBin
   * @param string  $variable
   */
  private function removeVar(Request &$request, $parameterBin, $variable)
  {
    if ($request->$parameterBin->has($variable)) {
      $request->$parameterBin->set($variable, '**REMOVED**');
    }
  }

  /**
   * Returns the request as a string.
   *
   * @param Request $request
   *
   * @return string The request
   */
  private function getRequestString(Request &$request)
  {
    $string =
        sprintf('%s %s %s', $request->getMethod(), $request->getRequestUri(), $request->server->get('SERVER_PROTOCOL')) . "\r\n\r\nRequest headers:\r\n" .
        $request->headers . "\r\n";

    // Check for request and get variables
    if (count($request->request) > 0) {
      $string .= "Request 'request' variables:" .
          $this->arrayToString($request->request->all()) . "\r\n";
    }
    if (count($request->query) > 0) {
      $string .= "Request 'query' variables:" .
          $this->arrayToString($request->query->all());
    }

    return $string;
  }

  /**
   * Convert an array to string
   *
   * @param     $array
   * @param int $currentDepth
   * @param int $maxDepth
   *
   * @return string
   */
  private function arrayToString($array, $currentDepth = 0, $maxDepth = 3)
  {
    if ($currentDepth >= $maxDepth) {
      return '**Maximum recursion level reached**';
    }

    $max = max(array_map('strlen', array_keys($array))) + 1;
    ksort($array);
    $return = '';

    // Loop the array and get the information
    foreach ($array as $name => $value) {
      if (is_array($value) || $value instanceof \Traversable) {
        $value = $this->arrayToString($value, $currentDepth + 1);
      }
      $return .= "\r\n" . str_repeat("\t", $currentDepth) . sprintf("%-{$max}s %s", $name . ':', $value);
    }

    return $return;
  }

  /**
   * Flushes the Swift Mailer transport queue
   */
  private function sendMessage()
  {
    $transport = $this->mailer->getTransport();
    if ($transport instanceof \Swift_Transport_SpoolTransport) {
      $spool = $transport->getSpool();
      if ($spool instanceof \Swift_FileSpool) {
        $spool->recover();
      }
      $spool->flushQueue($this->mailerTransport);
    }
  }

}
