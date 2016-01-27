<?php

/*
 * This file is part of the MailCatcher service provider for the Codeception Email Testing Framework.
 * (c) 2015-2016 Eric Martel
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

  /**
   * Codeception exposed variables
   *
   * @var array
   */
  protected $config = array('url', 'port', 'guzzleRequestOptions', 'deleteEmailsAfterScenario');

  /**
   * Codeception required variables
   *
   * @var array
   */
  protected $requiredFields = array('url', 'port');

  public function _initialize()
  {
    $url = trim($this->config['url'], '/') . ':' . $this->config['port'];

    $this->mailcatcher = new \GuzzleHttp\Client(['base_uri' => $url, 'timeout' => 1.0]);

    if (isset($this->config['guzzleRequestOptions'])) {
        foreach ($this->config['guzzleRequestOptions'] as $option => $value) {
            $this->mailcatcher->setDefaultOption($option, $value);
        }
    }
  }

  /** 
   * Method executed after each scenario
   */
  public function _after(\Codeception\TestCase $test)
  {
    if(isset($this->config['deleteEmailsAfterScenario']) && $this->config['deleteEmailsAfterScenario'])
    {
      $this->deleteAllEmails();
    }
  }

  /** 
   * Delete All Emails
   *
   * Accessible from tests, deletes all emails 
   */
  public function deleteAllEmails()
  {
    try
    {
      $this->mailcatcher->request('DELETE', '/messages');
    }
    catch(Exception $e)
    {
      $this->fail('Exception: ' . $e->getMessage());
    }
  }

  /** 
   * Fetch Emails
   *
   * Accessible from tests, fetches all emails 
   */
  public function fetchEmails()
  {
    $this->fetchedEmails = array();

    try
    {
      $response = $this->mailcatcher->request('GET', '/messages');
      $this->fetchedEmails = json_decode($response->getBody());
    }
    catch(Exception $e)
    {
      $this->fail('Exception: ' . $e->getMessage());
    }

    $this->sortEmails($this->fetchedEmails);

    // by default, work on all emails
    $this->setCurrentInbox($this->fetchedEmails);
  }

    /** 
   * Access Inbox For
   * 
   * Filters emails to only keep those that are received by the provided address
   *
   * @param string $address Recipient address' inbox
   */
  public function accessInboxFor($address)
  {
    $inbox = array();
    $addressPlusDelimiters = '<' . $address . '>';

    foreach($this->fetchedEmails as &$email)
    {
      if(in_array($addressPlusDelimiters, $email->recipients))
      {
        array_push($inbox, $email);
      }
    }
    $this->setCurrentInbox($inbox);
  }


  /**
   * Get Full Email
   * 
   * Returns the full content of an email
   *
   * @param string $id ID from the header
   * @return mixed Returns a JSON encoded Email
   */
  protected function getFullEmail($id)
  {
    try
    {
      $response = $this->mailcatcher->request('GET', "/messages/{$id}.json");
    }
    catch(Exception $e)
    {
      $this->fail('Exception: ' . $e->getMessage());
    }
    $fullEmail = json_decode($response->getBody());
    return $fullEmail;
  }

  /**
   * Get Email Subject
   *
   * Returns the subject of an email
   *
   * @param mixed $email Email
   * @return string Subject
   */
  protected function getEmailSubject($email)
  {
    return $email->subject;
  }

  /**
   * Get Email Body
   *
   * Returns the body of an email
   *
   * @param mixed $email Email
   * @return string Body
   */
  protected function getEmailBody($email)
  {
    $extension = "html";
    if(!in_array($extension, $email->formats))
    {
      $extension = "plain";
    }

    try
    {
      $response = $this->mailcatcher->request('GET', "/messages/{$email->id}.{$extension}");
      return $response->getBody()->getContents();
    }
    catch(Exception $e)
    {
      $this->fail('Exception: ' . $e->getMessage());
    }
    return "";
  }

  /**
   * Text After String
   * 
   * Returns the text after the given string, if found
   * 
   * @param string $haystack
   * @param string $needle
   * @return string Found string
   */
  protected function textAfterString($haystack, $needle)
  {
    $result = "";
    $needleLength = strlen($needle);

    if($needleLength > 0 && preg_match("#$needle([^\r\n]+)#i", $haystack, $match))
    {
      $result = trim(substr($match[0], -(strlen($match[0]) - $needleLength)));
    }

    return $result;
  }

  /**
   * Get Email To
   *
   * Returns the string containing the persons included in the To field
   *
   * @param mixed $email Email
   * @return string To
   */
  protected function getEmailTo($email)
  {
    // Reply-To appears first in the string, so lead To with \n
    return $this->textAfterString($email->source, "\nTo: ");
  }

  /**
   * Get Email CC
   *
   * Returns the string containing the persons included in the CC field
   *
   * @param mixed $email Email
   * @return string CC
   */
  protected function getEmailCC($email)
  {
    return $this->textAfterString($email->source, "Cc: ");
  }

  /**
   * Get Email BCC
   *
   * Returns the string containing the persons included in the BCC field
   *
   * @param mixed $email Email
   * @return string BCC
   */
  protected function getEmailBCC($email)
  {
    return $this->textAfterString($email->source, "Bcc: ");
  }

  /**
   * Get Email Recipients
   *
   * Returns the string containing all of the recipients, such as To, CC and if provided BCC
   *
   * @param mixed $email Email
   * @return string Recipients
   */
  protected function getEmailRecipients($email)
  {
    return $this->getEmailTo($email) . ' ' . $this->getEmailCC($email) . ' ' . $this->getEmailBCC($email);
  }

  /**
   * Get Email Sender
   *
   * Returns the string containing the sender of the email
   *
   * @param mixed $email Email
   * @return string Sender
   */
  protected function getEmailSender($email)
  {
    return $this->textAfterString($email->source, "From: ");
  }

  /**
   * Get Email Reply To
   *
   * Returns the string containing the address to reply to
   *
   * @param mixed $email Email
   * @return string ReplyTo
   */
  protected function getEmailReplyTo($email)
  {
    return $this->textAfterString($email->source, "Reply-To: ");
  }

  /**
   * Get Email Priority
   * 
   * Returns the priority of the email
   * 
   * @param mixed $email Email
   * @return string Priority
   */
  protected function getEmailPriority($email)
  {
    return $this->textAfterString($email->source, "X-Priority: ");
  }


  /** 
   * Open Next Unread Email
   *
   * Pops the most recent unread email and assigns it as the email to conduct tests on
   */
  public function openNextUnreadEmail()
  {
    $this->openedEmail = $this->getMostRecentUnreadEmail();
  }

  /**
   * Get Opened Email
   *
   * Main method called by the tests, providing either the currently open email or the next unread one
   *
   * @param bool $fetchNextUnread Goes to the next Unread Email
   * @return mixed Returns a JSON encoded Email
   */
  protected function getOpenedEmail($fetchNextUnread = FALSE)
  {
    if($fetchNextUnread || $this->openedEmail == NULL)
    {
      $this->openNextUnreadEmail();
    }

    return $this->openedEmail;
  }

  /**
   * Get Most Recent Unread Email
   * 
   * Pops the most recent unread email, fails if the inbox is empty
   * 
   * @return mixed Returns a JSON encoded Email
   */
  protected function getMostRecentUnreadEmail()
  {
    if(empty($this->unreadInbox))
    {
      $this->fail('Unread Inbox is Empty');
    }

    $email = array_shift($this->unreadInbox);
    return $this->getFullEmail($email->id);
  }

  /**
   * Set Current Inbox
   *
   * Sets the current inbox to work on, also create a copy of it to handle unread emails
   *
   * @param array $inbox Inbox
   */
  protected function setCurrentInbox($inbox)
  {
    $this->currentInbox = $inbox;
    $this->unreadInbox = $inbox;
  }

  /**
   * Get Current Inbox
   *
   * Returns the complete current inbox
   *
   * @return array Current Inbox
   */
  protected function getCurrentInbox()
  {
    return $this->currentInbox;
  }

  /**
   * Get Unread Inbox
   *
   * Returns the inbox containing unread emails
   *
   * @return array Unread Inbox
   */
  protected function getUnreadInbox()
  {
    return $this->unreadInbox;
  }

  /**
   * Sort Emails
   *
   * Sorts the inbox based on the timestamp
   *
   * @param array $inbox Inbox to sort
   */
  protected function sortEmails($inbox)
  {
    usort($inbox, array($this, 'sortEmailsByCreationDatePredicate'));
  }

  /**
   * Get Email To
   *
   * Returns the string containing the persons included in the To field
   *
   * @param mixed $emailA Email
   * @param mixed $emailB Email
   * @return int Which email should go first
   */
  static function sortEmailsByCreationDatePredicate($emailA, $emailB) 
  {
    $sortKeyA = $emailA->created_at;
    $sortKeyB = $emailB->created_at;
    return ($sortKeyA > $sortKeyB) ? -1 : 1;
  }
}
