<?php

namespace Drupal\apsis_mail\Exception;

/**
 * Busy exception
 *
 * Used if the API server is currently not available.
 */
class BusyException extends ApsisException
{

  /**
   * {@inheritdoc}
   */
  public static function getState()
  {
    return -5;
  }

  /**
   * {@inheritdoc}
   */
  public static function getHttpStatus()
  {
    return 503;
  }

}
