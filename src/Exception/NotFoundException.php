<?php

namespace Drupal\apsis_mail\Exception;

/**
 * Not found exception.
 *
 * Used if the URL is incorrect and no API method is found to deal with the request.
 */
class NotFoundException extends ApsisException
{

  /**
   * {@inheritdoc}
   */
  public static function getState()
  {
    return -3;
  }

  /**
   * {@inheritdoc}
   */
  public static function getHttpStatus()
  {
    return 404;
  }

}
