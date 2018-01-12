<?php

namespace Drupal\apsis_mail\Exception;

/**
 * Internal Server Error.
 *
 * Used if there was an issue with the API server. You should contact APSIS
 * support with the request details to get this resolved.
 */
class InternalServerErrorException extends ApsisException
{

  /**
   * {@inheritdoc}
   */
  public static function getState()
  {
    return -1;
  }

  /**
   * {@inheritdoc}
   */
  public static function getHttpStatus()
  {
    return 500;
  }

}
