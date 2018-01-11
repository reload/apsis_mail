<?php

namespace Drupal\apsis_mail\Exception;


use Throwable;

/**
 * Thrown if an email does not correspond to a subscriber in APSIS.
 */
class InvalidSubscriberException extends ValidationErrorException
{

  /**
   * {@inheritdoc}
   */
  public function __construct($message, $state = NULL, $code = NULL, Throwable $previous = null)
  {
    parent::__construct($message, $state, $code, $previous);
  }

  /**
   * {@inheritdoc}
   */
  public function getMatchPhrase()
  {
    return '/no subscriber with email/';
  }

}
