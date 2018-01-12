<?php

namespace Drupal\apsis_mail\Exception;

/**
 * Thrown if an email cannot be added to a subscription list because the user is
 * on an opt-out list.
 *
 * Opt out list is when a subscriber opts out (opts not to receive more emails)
 * from a specific list in APSIS Pro. A subscriber's email address that ends up
 * on the "Opt out list" for a certain list in APSIS Pro cannot be added to that
 * list again until a) the email address has been deleted from that "Opt out
 * list" list or b) the email address re-subscribes to that list account through
 * an APSIS Pro generated subscription form (form builder available in the APSIS
 * Pro GUI). Opting out from a list does mean you may still be a subscriber to
 * another list on that same account.
 *
 * Opt out all is when a subscriber opts out (opts not to receive more emails)
 * from an APSIS Pro account (i.e most often an APSIS customer). A subscriber's
 * email address that ends up on the "Opt out all" list in APSIS Pro cannot be
 * added to the account again until a) the email address has been deleted from
 * the Opt out all list or b) the email address re-subscribes to the account
 * through an APSIS Pro generated subscription form (form builder available in
 * the APSIS Pro GUI).
 *
 * @see http://se.apidoc.anpdm.com/Help/Terminology/Terminology
 */
class OptOutSubscriberException extends ValidationErrorException
{

  /**
   * {@inheritdoc}
   */
  public static function getMatchPhrase()
  {
    // Message in the format
    // "Subscriber with e-mail foo@bar.com exists on the Opt-out List."
    return '/exists on the Opt-out/';
  }

}
