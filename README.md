# MailClientAutoConfig
PHP script to help serve Outlook [autodiscover.xml](https://msdn.microsoft.com/en-us/library/cc463896%28v=exchg.80%29.aspx) as well as Mozilla [autoconfig](https://developer.mozilla.org/en-US/docs/Mozilla/Thunderbird/Autoconfiguration) files.

Instead of making your users manually configure their email clients, you can publish the necessary settings on a web server. Several popular mail clients support this feature and will automatically configure based on an email address and a password. 

This script aims to help you in this endeavor. It currently supports two different standards, used by different mail clients:
* Microsoft Outlook
* Mozilla Thunderbird
* Evoluton
* KMail
* Kontact

Things this script does for you:
* Support multiple standards based on a single configuration file
* Determine the username to use based on the email address.<br/>
  Some setups use the full mail address, others use the local part, yet others have no fixed relation between the two.
  This script can be especially helpful in the latter case, since it supports reading /etc/mail/aliases type files commonly found in Linux setups. You can also define your own mapping using for example a database, but this will require some additional programming.

## Requirements
* A web server capable of running PHP >= 7.3 scripts and URL rewriting
* A **SSL certificate** for **autoconfig.yourdomain.com**, **autodiscover.yourdomain.com**, as well as any additional domains you use. The most crucial of these is autodiscover.yourdomain.com, which is used by Outlook, which will use HTTPS to obtain its settings by default. It may revert to HTTP if the user persists, but will complain loudly before doing so.
* DNS entries for the above host names pointing to the web server

While pretty much any web server on any platform will do, only Apache 2 will be covered here in detail for now. 

## Setup using Apache 2

In this example, we'll be setting things up for the example.com mail server, running Linux and Apache 2. The organization also uses a number of mail addresses on example.org, hosted on the same mail server. Some of the details are glanced over here because they differ per Linux distro.

1. Create a new directory that will contain the web site. In this example, we'll use `/var/www/autoconfig`
2. Place the code from folder `src` inside the new directory
3. Copy or rename autoconfig.settings.sample.php to autoconfig.settings.php
4. Create a new VirtualHost configuration in Apache:
   ```
   <VirtualHost *:80>
     ServerAdmin postmaster@example.com
     DocumentRoot /var/www/autoconfig
     ServerName autoconfig.example.com
     ServerAlias autoconfig.example.org autodiscover.example.com autodiscover.example.org
     ErrorLog /var/log/apache2/autoconfig-error.log
     TransferLog /var/log/apache2/autoconfig-access.log
  
     RewriteEngine On
     RewriteRule ^/mail/config-.*\.xml$ /autoconfig.php
     RewriteRule ^/autodiscover/autodiscover.xml$ /autoconfig.php
   </VirtualHost>
   ```
5. Repeat the same configuration for `<VirtualHost *:443>` and add your SSL configuration (`SSLCertificateFile`, `SSLCertificateKeyFile`, etc.) Alternatively, if you use [Let's Encrypt](https://letsencrypt.org/) for your free SSL certificate, you can let the command line tool take care of this for you afterwards.
6. You may want to disable directory listing if it's not disabled by default:
   ```
   <Directory /var/www/autoconfig>
     Options -Indexes
   </Directory>
   ```
6. Edit the autoconfig.settings.php file using your favorite text editor. See below for configuration details.
7. Restart Apache

## Setup using Nginx

In this example, we'll be setting things up for the example.com mail server, running Linux and Nginx. The organization also uses a number of mail addresses on example.org, hosted on the same mail server. Some of the details are glanced over here because they differ per Linux distro.

1. Create a new directory that will contain the web site. In this example, we'll use `/var/www/autoconfig`
2. Place the code from folder `src` inside the new directory
3. Copy or rename autoconfig.settings.sample.php to autoconfig.settings.php
4. Create a new VirtualHost configuration in Nginx:
   ```
    server {
        listen 80;
        server_name autoconfig.example.com autoconfig.example.org autodiscover.example.com autodiscover.example.org;
        root /var/www/autoconfig;
    
        location ~ ^/mail/config-.*\.xml$ {
            index autoconfig.php;
        }
        location ~ ^/autodiscover/autodiscover.xml$ {
            index autoconfig.php;
        }
    }
   ```
5. Repeat the same configuration for `listen 443 ssl` and add your SSL configuration (`ssl_certificate`, `ssl_certificate_key`, etc.) Alternatively, if you use [Let's Encrypt](https://letsencrypt.org/) for your free SSL certificate, you can let the command line tool take care of this for you afterwards.
6. Edit the autoconfig.settings.php file using your favorite text editor. See below for configuration details.
7. Restart Apache

## Configuration

The configuration file is really just a normal PHP source file that is included into the main source file.
This means that there is nothing stopping you from adding custom code here, or from messing things up badly ;-)

Most of the configuration is explained inside the sample configuration file itself, but there are a few things to note:
* In the configuration file, you typically define at least two servers: one IMAP or POP3 server, and a SMTP server.
* Each server can have one or more endpoints. An endpoint is a combination of:
  * The TCP port number
  * The transport security (unencrypted, TLS or SSL)
  * The authentication mechanism.
* The order in which you define servers and their endpoints is significant.
  * Outlook will see only the first usable server for both incoming and outgoing mail.
  * Thunderbird will generally use the first one as well, although it appears to have a preference for SMTP with authentication over unauthenticated SMTP.


To continue the example started above, let's assume users have mail address both on the example.com and example.org domain.
Each of these domains have their own aliases file, `/etc/mail/domains/example.com/aliases` and `/etc/mail/domains/example.org/aliases`. We will use these files to determine the username for each mail alias.

```
<?php

$cfg = $config->add('example.com');
$cfg->set_name('Example mail services', 'Example');
$cfg->set_domains([ 'example.com', 'example.org' ]);
$aliasResolver = new AliasesFileUsernameResolver("/etc/mail/domains/$domain/aliases");
$cfg->set_username($aliasResolver);

$cfg->add_server('imap', 'mail.example.com')
    ->withEndpoint('STARTTLS')
    ->withEndpoint('SSL');

$cfg->add_server('smtp', 'mail.example.com')
    ->withEndpoint('STARTTLS')
    ->withEndpoint('SSL');
```

## Testing the setup

1. Visit http://autoconfig.example.com/mail/config-v1.1.xml?emailaddress=info@example.com (where info@example.com is a valid email address delivered to a single local mailbox).
   * You should see an XML file in which you recognize the settings you've provided.
   * If you get an empty response, check the error log of your web server.
2. Visit https://autoconfig.example.com/mail/config-v1.1.xml?emailaddress=info@example.com to test your SSL setup.
   * You should see the same file.
   * If this does not work, check your SSL setup.
3. If everything appears to work, test by adding the mail account to Thunderbird and Outlook itself.

## Credits
Original code from [Thorarin](https://github.com/Thorarin) in his [Repository](https://github.com/Thorarin/MailClientAutoConfig)