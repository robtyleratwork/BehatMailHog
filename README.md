# Behat MailHog Context Class

Adds Behat steps that examine the content of the last email captured by MailHog.

## Behat Steps
### Checking From, To and Subject
To test the value of the *from*, *to* or *subject* fields use the following
steps. Only one email address is supported. Most websites send email directly to a single
address from a single address.
```
Then I should see last email to is "Bob User <hello@bobuser.me>"
And I should see last email subject is "Greetings from Bob User"
```
### Checking Message Content
You can check the last email contains the correct content using either of the following methods:
```
Then I should see last email contains "this single-line content"
And I should see last email contains:
"""
The email should contain this...
...mult-line content
"""
```
### Following links
To follow a link in en email, provide the inner text of the anchor. The first matching link found
is used, subsequent links are ignored.
```
When I follow "click this link" in last email
```

## Installation

1. The [MinkExtension](https://github.com/Behat/MinkExtension) is required.
2. Clone the repository into your ```features/bootstrap``` directory.
2. Update your ```behat.yaml``` file to reference the new context class:
```
            contexts:
                - Behat\MinkExtension\Context\MinkContext
                - BehatMailHog\MailHogContext
```


