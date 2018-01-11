<?php

namespace Drupal\apsis_mail\Exception;

use Throwable;

/**
 * Generic Apsis exception.
 *
 * Thrown if an error occours in the communication with the APSIS API.
 *
 * @see http://se.apidoc.anpdm.com/Help/GettingStarted/Getting%20started
 */
class ApsisException extends \Exception {

  /**
   * APSIS custom error code.
   *
   * @var int
   */
  protected $state;

  /**
   * ApsisException constructor.
   *
   * @param string $message
   *   Exception message.
   * @param int $state
   *   Apsis custom error code.
   * @param int $code
   *   HTTP error code.
   * @param Throwable|null $previous
   *   Exception which this message was mapped from.
   */
  public function __construct($message, $state, $code = 0, Throwable $previous = null)
  {
    parent::__construct($message, $code, $previous);
    $this->state = $state;
  }

  /**
   * Returns the custom APSIS error code.
   *
   * @return int
   *   Custom APSIS error code
   */
  public function getState()
  {
    return $this->state;
  }

  /**
   * Return a regular expression which can be used to match against a custom APSIS error message.
   *
   * If the message matches the expression then the exception should be mapped to this type.
   *
   * @return string|NULL
   *   Regular expression. NULL if the Apsis exception cannot be mapped using a match phrase.
   */
  public function getMatchPhrase()
  {
    return NULL;
  }

}
