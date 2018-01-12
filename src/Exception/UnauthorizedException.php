<?php

namespace Drupal\apsis_mail\Exception;

/**
 * Unauthorized exception.
 *
 * Represents lack of access rights to perform the API request, for example
 * using an invalid API key.
 */
class UnauthorizedException extends ApsisException {

  /**
   * {@inheritdoc}
   */
  public static function getState()
  {
    return -4;
  }

  /**
   * {@inheritdoc}
   */
  public static function getHttpStatus()
  {
    return 403;
  }

}
