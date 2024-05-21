<?php

namespace Kickin\ExceptionHandlerBundle\Backtrace;

use Exception;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

use Kickin\ExceptionHandlerBundle\Exceptions\FileAlreadyExistsException;
use Kickin\ExceptionHandlerBundle\Exceptions\UploadFailedException;

/**
 * This objects manages a backtracelog (it saves it in a file and returns the proper file name of where the backtrace
 * log is saved).
 *
 * @author Wendo
 */
class BacktraceLogFile
{
  /**
   * Name of the backtrace file
   *
   * @var string
   */
  private $name;

  /**
   * Contents of the backtrace
   *
   * @var string
   */
  private $fileContent;

  /**
   * Base folder used to save the data
   */
  private $folder;

  /**
   * @throws Exception
   */
  public function __construct(string $folder)
  {
    $this->folder = $folder;
    $this->generateNewName();
  }

  /**
   * Returns the name of the file that will be uploaded
   */
  public function getName(): string
  {
    return $this->name;
  }

  /**
   * Generates a new, unique name
   *
   * @throws Exception
   */
  public function generateNewName(): void
  {
    $this->name = $this->folder . "/" . bin2hex(random_bytes(20)) . ".btl";
  }

  /**
   * Sets the content for the file to be uploaded
   */
  public function setFileContent(string $code): self
  {
    $this->fileContent = $code;

    return $this;
  }

  /**
   * Returns the content of the backtrace file that will be uploaded
   */
  public function getFileContent(): string
  {
    return $this->fileContent;
  }

  /**
   * Upload the file. Returns true if the upload was successful, else throws
   *
   * @throws UploadFailedException|FileAlreadyExistsException
   */
  public function uploadFile(): bool
  {
    $fs = new Filesystem();

    //check if file already exists
    if (!$fs->exists($this->name)) {
      // File doesn't exist
      try {
        $fs->dumpFile($this->name, $this->fileContent);
      } catch (IOException $e){
        throw new UploadFailedException();
      }
    } else {
      // File does exist, throw error
      throw new FileAlreadyExistsException();
    }

    return true;
  }
}

