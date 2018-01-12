<?php

namespace Drupal\apsis_mail\Exception;

/**
 * API Disabled exception.
 *
 * Used if the API service is temporarily offline for maintenance and not
 * available at the moment.
 */
class ApiDisabledException extends ApsisException
{

  /**
   * {@inheritdoc}
   */
  public static function getState()
  {
    return -6;
  }

  /**
   * {@inheritdoc}
   */
  public static function getHttpStatus()
  {
    return 503;
  }

}
