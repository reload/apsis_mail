<?php

namespace Drupal\apsis_mail\Exception;

/**
 * Thrown if an email does not correspond to a subscriber in APSIS.
 */
class InvalidSubscriberException extends ValidationErrorException
{

  /**
   * {@inheritdoc}
   */
  public static function getMatchPhrase()
  {
    return '/no subscriber with email/';
  }

}
