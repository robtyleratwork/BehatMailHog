<?php

namespace BehatMailHog;

use Behat\Behat\Context\Context;
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Gherkin\Node\PyStringNode;

/**
 * Class MailHogContext
 *
 * Adds Behat steps that enables the user to interact with email data
 * extracted from the MAMP BehatMailHog log.
 */
class MailHogContext extends RawMinkContext implements Context
{
    /** @var string URL to the BehatMailHog API messages endpoint */
    private static $mailHogApiMessagesEndPoint = 'http://0.0.0.0:8025/mailhog/api/v2/messages';

    /**
     * MailHogContext constructor.
     * @param string $mailHogApiMessagesEndPoint
     */
    public function __construct(string $mailHogApiMessagesEndPoint = '')
    {
        if (!empty($mailHogApiMessagesEndPoint))
            self::$mailHogApiMessagesEndPoint = $mailHogApiMessagesEndPoint;
    }

    /**
     * @Then I debug last email
     *
     * Dumps out the data gathered for the last email from the log file.
     */
    public function iDebugLastEmail()
    {
        var_dump($this->getLastEmail());
    }

    /**
     * @Then I should see last email :field is :subject
     *
     * Compare an expected email 'field' value against the actual value.
     *
     * @param string $field
     * @param string $value
     */
    public function iShouldSeeLastEmailIs(string $field, string $value)
    {
        $emailData = $this->getLastEmail();
        $field = strtolower($field);

        if (!in_array($field, ['from', 'subject', 'to']))
            throw new \RuntimeException ("Email field '{$field}' is not valid. ");
        else if (empty($emailData[$field]))
            throw new \RuntimeException ("Could not find email '{$field}' field");
        else if ($emailData[$field] !== $value)
            throw new \RuntimeException ("Email field '{$field}' does not match expected value. Field is set to '{$emailData[$field]}'");
    }

    /**
     * @Then I should see last email contains :content
     *
     * Check the last email message contains the given single-line text.
     *
     * @param string $content
     */
    public function iShouldSeeLastEmailContains(string $content)
    {
        $emailData = $this->getLastEmail();

        if (!strstr($emailData['html'], $content))
            throw new \RuntimeException ("Could not find content in email message");
    }

    /**
     * @Then I should see last email contains:
     *
     * Check the last email message contains the given multi-line text.
     *
     * @param PyStringNode $pyStringNode
     */
    public function iShouldSeeLastEmailContainsPyStringNode(PyStringNode $pyStringNode)
    {
        $this->iShouldSeeLastEmailContains(trim((string) $pyStringNode));
    }

    /**
     * @When I follow :linkText in last email
     *
     * Searches the email HTML content for a link URL matching the given text.
     * If found, the user is redirected to the URL.
     *
     * @param string $linkText
     */
    public function iFollowInLastEmail(string $linkText)
    {
        $emailData = $this->getLastEmail();
        preg_match_all("/<a.*href=\"(.*)\".*>.*<\/a>/xUs", $emailData['html'], $matches);
        $url = null;

        // Loop over all the links found in the email HTML content and record a match.
        foreach ($matches[0] as $index => $link)
            if (strip_tags($link) === $linkText && empty($url))
                $url = $matches[1][$index];

        if (!empty($url))
            $this->visitPath($url);
        else
            throw new \RuntimeException("Unable to find link matching '{$linkText}' to follow in email");
    }

    /**
     * Request messages from BehatMailHog using the API and return
     * some data from the last email received.
     *
     * @return array
     */
    public function getLastEmail()
    {
        $messages = json_decode($this->getMessagesFromApi());

        if (!is_object($messages))
            throw new \RuntimeException("Could not retrieve BehatMailHog messages from API");
        else if (empty($messages->items))
            throw new \RuntimeException("There are no messages in BehatMailHog");

        $latestItem = current($messages->items);
        $headers = $latestItem->Content->Headers;
        $emailData = [];

        $emailData['date'] = current($headers->Date);
        $emailData['from'] = current($headers->From);
        $emailData['to'] = current($headers->To);
        $emailData['subject'] = current($headers->Subject);
        // Get the HTML body and tidy up the formatting.
        $emailData['html'] = $latestItem->MIME->Parts[1]->Body;
        $emailData['html'] = str_replace(['=\r\n', '\r\n'], ['', "\r\n"], $emailData['html']);
        $emailData['html'] = stripslashes(quoted_printable_decode(($emailData['html'])));

        return $emailData;
    }

    /**
     * Get messages from the BehatMailHog API.
     *
     * @return bool|mixed|string
     */
    public function getMessagesFromApi()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::$mailHogApiMessagesEndPoint);
        // Make the curl_exec return thr result rather than output on the command line.
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error)
            throw new \RuntimeException("Problem connecting to BehatMailHog messages endpoint: " . print_r($error, true));

        return $result;
    }
}
