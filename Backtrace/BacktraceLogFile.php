<?php

namespace KickIn\ExceptionHandlerBundle\Backtrace;

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
   * Constructor -> generate an unique name
   *
   * @param string $folder
   */
  public function __construct($folder)
  {
    $this->folder = $folder;
    $this->generateNewName();
  }

  /**
   * Returns the name of the file that will be uploaded
   *
   * @return string
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * Generates a new, unique name
   */
  public function generateNewName()
  {
    $this->name = $this->folder . "/" . bin2hex(random_bytes(20)) . ".btl";
  }

  /**
   * Sets the content for the file to be uploaded
   *
   * @param $code
   *
   * @return $this
   */
  public function setFileContent($code)
  {
    $this->fileContent = $code;

    return $this;
  }

  /**
   * Returns the content of the backtrace file that will be uploaded
   *
   * @return string
   */
  public function getFileContent()
  {
    return $this->fileContent;
  }

  /**
   * Upload the file. Returns the true if it the upload was succesful, else returns false
   *
   * @return bool
   * @throws UploadFailedException|FileAlreadyExistsException
   */
  public function uploadFile()
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

