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
   * Returns the custom APSIS error code corresponding to this exception type.
   *
   * @return int
   *   Custom APSIS error code
   */
  public static function getState() {
    return NULL;
  }


  /**
   * Returns the HTTP status code corresponding to this exception type.
   *
   * @return int
   *   Custom APSIS error code
   */
  public static function getHttpStatus() {
   return NULL;
  }

  /**
   * Return a regular expression which can be used to match against a custom APSIS error message.
   *
   * If the message matches the expression then the exception should be mapped to this type.
   *
   * @return string|NULL
   *   Regular expression. NULL if the Apsis exception cannot be mapped using a match phrase.
   */
  public static function getMatchPhrase() {
    return NULL;
  }

}
