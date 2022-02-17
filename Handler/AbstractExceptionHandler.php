<?php

namespace Kickin\ExceptionHandlerBundle\Handler;

use DateTime;
use Exception;
use Kickin\ExceptionHandlerBundle\Backtrace\BacktraceLogFile;
use Kickin\ExceptionHandlerBundle\Configuration\ConfigurationInterface;
use Kickin\ExceptionHandlerBundle\Exceptions\FileAlreadyExistsException;
use Kickin\ExceptionHandlerBundle\Exceptions\UploadFailedException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Traversable;

/**
 * This Service triggers on the kernel.exception and catches any HTTP 500 error. When an HTTP 500 error is detected,
 * an e-mail is send to maintainers.
 *
 * Pick one of the available implementations, based on your mailer configuration.
 *
 * @author Wendo
 * @author BobV
 */
abstract class AbstractExceptionHandler
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
   * @var TokenStorageInterface
   */
  protected $tokenStorage;

  /**
   * @var ConfigurationInterface
   */
  protected $configuration;

  /**
   * AbstractExceptionHandler constructor.
   *
   * @param TokenStorageInterface  $tokenStorage
   * @param ConfigurationInterface $configuration
   */
  public function __construct(TokenStorageInterface $tokenStorage, ConfigurationInterface $configuration)
  {
    $this->tokenStorage  = $tokenStorage;
    $this->configuration = $configuration;
  }

  /**
   * @return array
   *
   * We need to handle both events: the KernelException event is used to catch any Exception event,
   * while the KernelResponse event is used to determine if the 500 error message is actually displayed to the user.
   * This ensures that NotAuthorizedExceptions are not mailed as a 500 error (as a 403 error is presented).
   */
  static protected function getSubscribedEvents()
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
   * @param ExceptionEvent $event
   *
   * @throws Exception
   */
  public function onKernelException(ExceptionEvent $event)
  {
    // Skip some exception types directly
    $exception = $event->getThrowable();
    if ($exception instanceof NotFoundHttpException ||
        $exception instanceof AccessDeniedHttpException ||
        $exception instanceof BadRequestHttpException ||
        $exception instanceof GoneHttpException) {
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

    if ($status === self::FILE_SAVE_SUCCESS) {
      // Uploading of the file succeeded
      // Backtrace has been uploaded -> put the address in a session variable
      $fileName = $backtrace->getName();
      if ($session) {
        $session->set(self::SESSION_FILENAME, $fileName);
      }
    } else {
      // Uploading went wrong -> just mail the backtrace so we have saved it.
      $this->sendExceptionHandledFailedMessage($uploadException, $backtrace);
    }
  }

  /**
   * This function builds a backtrace
   *
   * @param ExceptionEvent $event
   *
   * @return string
   */
  protected function buildBacktrace(ExceptionEvent $event)
  {
    $user = $this->configuration->getUserInformation($this->tokenStorage->getToken());

    // Build backtrace
    $comment = "Exception Code: " . $event->getThrowable()->getCode();
    $comment .= "\nThe class of the exception: " . get_class($event->getThrowable());
    $comment .= "\nThe user who triggered the exception: " . $user;
    $comment .= "\nFile in which the exception occured: " . $event->getThrowable()->getFile();
    $comment .= "\nException message: " . $event->getThrowable()->getMessage();
    $comment .= "\n\nThe backtrace:\n" . $event->getThrowable()->getTraceAsString();

    return $comment;
  }

  /**
   * This function handles the Kernel exception response. When the response contains an http 500 error, it is tried to
   * get the backtrace from the server (via the file name stored in the session) and mail this to the maintainers
   *
   * @param ResponseEvent $responseObject
   *
   * @throws Exception
   */
  public function onKernelResponse(ResponseEvent $responseObject)
  {
    $response   = $responseObject->getResponse();
    $request    = $responseObject->getRequest();
    $statusCode = $response->getStatusCode();
    $session    = $request->getSession();

    if (!$this->configuration->isProductionEnvironment()) {
      // no production! -> don't sent an email
      if (!$session || !$session->has(self::SESSION_FILENAME)) return;

      try {
        $fs = new Filesystem();
        $fs->remove($session->get(self::SESSION_FILENAME));
      } catch (Exception $e) {
        // Do nothing when this fails
      }
      $session->remove(self::SESSION_FILENAME);

      return;
    }

    // Check if exception is an HTTP 500 error code
    if ($statusCode != 500) return;

    // HTTP 500 error code found -> send email

    // Get the routing and some other stuff out of the request
    $method     = $request->getMethod();
    $requestUri = $request->getRequestUri();
    $baseUrl    = $request->getHost();

    // Remove any personal stuff from the request variables
    $this->removeVars($request, 'request', [
        'password', '_password',
    ]);
    $this->removeVars($request, 'server', [
        'COOKIE', 'HTTP_AUTHORIZATION', 'HTTP_COOKIE', 'PHP_AUTH_PW',
    ]);
    $this->removeVars($request, 'headers', [
        'authorization', 'cookie', 'php-auth-pw',
    ]);
    $this->removeVars($request, 'cookies', [
        'PHPSESSID', 'REMEMBERME',
    ]);

    // Get the POST variables
    $responseString   = $response->__toString();
    $requestString    = $this->getRequestString($request);
    $responseContent  = $response->getContent();
    $responseString   = explode($responseContent, $responseString);
    $sessionVariables = $session ? $session->all() : [];
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

    if ($session) {
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
    }

    // Retrieve backtrace contents
    $fs            = new Filesystem();
    if ($session) {
      $backtraceFile = $session->get(self::SESSION_FILENAME);
      $backtrace     = file_get_contents($backtraceFile);
    } else {
      $backtrace = null;
    }
    if (!$backtrace) {
      $backtrace = "FILE NOT FOUND. THE BACKTRACE FILE COULDN'T BE FOUND ON THE SERVER. TOO BAD... MAYBE HAVE A LOOK?\n\n File: $backtraceFile";
    }

    // Try to delete the file, when failed extend the mail body with a notification of this failure
    try {
      $fs->remove($backtraceFile);
    } catch (IOException $e) {
      $extension = "\n\n Oh and by the way, it failed to remove the backtrace file (name: " . $backtraceFile . "). Then you know that ;)\n\n";
    }

    $this->sendExceptionMessage(
        $session, $requestUri, $baseUrl, $method, $requestString, $responseString,
        $backtrace, $serverVariablesString, $globalVariablesString, $extension ?? '');

    // Unset session
    if ($session) {
      $session->remove(self::SESSION_FILENAME);
      $session->remove(self::SESSION_ERROR);
    }
  }

  /**
   * @param $url
   * @param $exceptionMessage
   *
   * @return string
   */
  protected function generateHash($url, $exceptionMessage)
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
  protected function removeVar(Request $request, $parameterBin, $variable)
  {
    if ($request->$parameterBin->has($variable)) {
      $request->$parameterBin->set($variable, '**REMOVED**');
    }
  }

  /**
   * Removes a variables from the request
   *
   * @param Request $request
   * @param string  $parameterBin
   * @param array   $variables
   */
  protected function removeVars(Request $request, $parameterBin, array $variables)
  {
    foreach ($variables as $variable) {
      $this->removeVar($request, $parameterBin, $variable);

      if ($parameterBin === 'server') {
        $this->removeVar($request, $parameterBin, 'REDIRECT_' . $variable);
      }
    }
  }

  /**
   * Returns the request as a string.
   *
   * @param Request $request
   *
   * @return string The request
   */
  protected function getRequestString(Request $request)
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
  protected function arrayToString($array, $currentDepth = 0, $maxDepth = 3)
  {
    if ($currentDepth >= $maxDepth) {
      return '**Maximum recursion level reached**';
    }

    $max = max(array_map('strlen', array_keys($array))) + 1;
    ksort($array);
    $return = '';

    // Loop the array and get the information
    foreach ($array as $name => $value) {
      if (is_array($value) || $value instanceof Traversable) {
        $value = $this->arrayToString($value, $currentDepth + 1);
      }
      $return .= "\r\n" . str_repeat("\t", $currentDepth) . sprintf("%-{$max}s %s", $name . ':', $value);
    }

    return $return;
  }

  /**
   * @param                  $uploadException
   * @param BacktraceLogFile $backtrace
   *
   * @throws Exception
   */
  abstract protected function sendExceptionHandledFailedMessage($uploadException, BacktraceLogFile $backtrace): void;

  /**
   * @param SessionInterface|null $session
   * @param string                $requestUri
   * @param string                $baseUrl
   * @param string                $method
   * @param string                $requestString
   * @param array                 $responseString
   * @param string                $backtrace
   * @param string                $serverVariablesString
   * @param string                $globalVariablesString
   * @param string                $extension
   *
   * @throws Exception
   */
  abstract protected function sendExceptionMessage(
      ?SessionInterface $session, string $requestUri, string $baseUrl, string $method,
      string $requestString, array $responseString, string $backtrace,
      string $serverVariablesString, string $globalVariablesString, string $extension): void;
}
