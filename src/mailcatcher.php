<?php

/*
 * This file is part of the MailCatcher service provider for the Codeception Email Testing Framework.
 * (c) 2015 Eric Martel
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Codeception\Module;

use Codeception\Module;

class MailCatcher extends Module
{
  use \Codeception\Email\TestsEmails;

  use \Codeception\Email\EmailServiceProvider;

  /**
   * HTTP Client to interact with MailCatcher
   *
   * @var \GuzzleHttp\Client
   */
  protected $mailcatcher;

  /**
   * Raw email header data converted to JSON
   *
   * @var array
   */
  protected $fetchedEmails;

  /**
   * Currently selected set of email headers to work with
   *
   * @var array
   */
  protected $currentInbox;

  /**
   * Starts as the same data as the current inbox, but items are removed as they're used
   *
   * @var array
   */
  protected $unreadInbox;

  /**
   * Contains the currently open email on which test operations are conducted
   *
   * @var mixed
   */
  protected $openedEmail;

}