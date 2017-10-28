<?php

namespace Kickin\ExceptionHandlerBundle\Handler;

use DateTime;
use Exception;
use KickIn\ExceptionHandlerBundle\Backtrace\BacktraceLogFile;
use KickIn\ExceptionHandlerBundle\Configuration\ConfigurationInterface;
use Kickin\ExceptionHandlerBundle\Configuration\EmptyConfiguration;
use Kickin\ExceptionHandlerBundle\Exceptions\FileAlreadyExistsException;
use Kickin\ExceptionHandlerBundle\Exceptions\UploadFailedException;
use Swift_Attachment;
use Swift_Mailer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Description of ExceptionHandler
 * This Service triggers on the kernel.exception and catches any HTTP 500 error. When an HTTP 500 error is detected,
 * an e-mail is send to maintainers.
 *
 * @author Wendo
 * @author BobV
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

  /**
   * @var Swift_Mailer
   */
  private $mailer;

  /**
   * @var TokenStorageInterface
   */
  private $tokenStorage;

  /**
   * @var \Twig_Environment
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
   * @param TokenStorageInterface  $tokenStorage
   * @param \Twig_Environment      $twig
   * @param ConfigurationInterface $configuration
   */
  public function __construct(Swift_Mailer $mailer, TokenStorageInterface $tokenStorage, \Twig_Environment $twig, ConfigurationInterface $configuration)
  {
    if ($configuration instanceof EmptyConfiguration) {
      throw new \InvalidArgumentException("You need to create your own configuration class and register it correctly in order to use this bundle!");
    }

    $this->mailer        = $mailer;
    $this->tokenStorage  = $tokenStorage;
    $this->twig          = $twig;
    $this->configuration = $configuration;
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
   * This function will create create the backtrace and save it with an random
   * name in /web/uploads/exceptionBacktrace/ and save the name in the session so
   * in the html status exception handler load the backtrace and send it in an email.
   *
   * @param GetResponseForExceptionEvent $event
   */
  public function onKernelException(GetResponseForExceptionEvent $event)
  {
    // If the thrown exception is caused by a HTTP 404 error (page not found) -> do nothing
    if ($event->getException() instanceof NotFoundHttpException) {
      return;
    }

    // If not production, return
    if (!$this->configuration->isProductionEnvironment()) return;

    // Create log file
    $backtrace = new BacktraceLogFile($this->configuration->getBacktraceFolder());
    $backtrace->setFileContent($this->buildBacktrace($event));

    // Unset the used session variable
    $session = $event->getRequest()->getSession();
    if ($session) {
      $session->remove(self::SESSION_FILENAME);
      $session->set(self::SESSION_ERROR, $event->getException()->getMessage());
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
      }
    } else {
      // Uploading went wrong -> just mail the backtrace so we have saved it.
      // Build email
      $message = $this->mailer->createMessage()
          ->setSubject("[iDB] onKernelException: Failed to upload backtrace")
          ->setFrom($this->configuration->getSender())
          ->setTo($this->configuration->getReceiver())
          ->setBody($this->twig->render('KickinExceptionHandlerBundle:Message:upload-failed.txt.twig', array(
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
   * @param GetResponseForExceptionEvent $event
   *
   * @return string
   */
  private function buildBacktrace(GetResponseForExceptionEvent $event)
  {
    if (is_object($this->tokenStorage->getToken())) {
      $secUser = $this->tokenStorage->getToken()->getUser();
      if (is_object($secUser) && $secUser->getPerson() !== NULL) {
        $user = $secUser->getPerson()->getFullName() . " (person id: " . $secUser->getPerson()->getId() . ")";
      } else {
        $user = 'No user (not authenticated)';
      }
    } else {
      $user = 'No user (not authenticated)';
    }

    // Build backtrace (copied from IdbIdbBundle\Translation\IdbTranslator.php
    $comment = "Exception Code: " . $event->getException()->getCode();
    $comment .= "\nThe class of the exception: " . get_class($event->getException());
    $comment .= "\nThe user who triggered the exception: " . $user;
    $comment .= "\nFile in which the exception occured: " . $event->getException()->getFile();
    $comment .= "\nException message: " . $event->getException()->getMessage();
    $comment .= "\n\nThe backtrace:\n" . $event->getException()->getTraceAsString();

    return $comment;
  }

  /**
   * This function handles the Kernel exception response. When the response contains an http 500 error, it is tried to
   * get the backtrace from the server (via the file name stored in the session) and mail this to the iDB service team
   *
   * @author Wendo
   *
   * @param FilterResponseEvent $responseObject
   */
  public function onKernelResponse(FilterResponseEvent $responseObject)
  {
    $response   = $responseObject->getResponse();
    $request    = $responseObject->getRequest();
    $statusCode = $response->getStatusCode();
    $session    = $request->getSession();

    // Check if exception is an HTTP 500 error code
    if ($statusCode == 500) {
      if (!$this->configuration->isProductionEnvironment()) {
        // no production! -> don't sent an email
        try {
          $fs = new Filesystem();
          $fs->remove($session->get(self::SESSION_FILENAME));
        } catch (Exception $e) {
          // Do nothing when this fails
        }
        $session->remove(self::SESSION_FILENAME);

        return;
      }

      // HTTP 500 error code found -> send email

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

      if ($session->get(self::SESSION_FILENAME) &&
          ($session->get(self::SESSION_PREVIOUS_HASH) != $hash || $previousDatetime < $now)) {

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
            ->setSubject("[iDB] Exception Handler: 500 error at " . $requestUri)
            ->setFrom($this->configuration->getSender())
            ->setTo($this->configuration->getReceiver())
            ->setBody($this->twig->render('KickinExceptionHandlerBundle:Message:exception.txt.twig', array(
                'user'          => $this->configuration->getUserInformation($this->tokenStorage->getToken()),
                'method'        => $method,
                'baseUrl'       => $baseUrl,
                'requestUr'     => $requestUri,
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

        // Unset session
        $session->remove(self::SESSION_FILENAME);
        $session->remove(self::SESSION_ERROR);
      }
    }
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

}
