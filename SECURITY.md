# Security Policy

We take the security of our system seriously, and we value the security community in helping us to secure our systems. 
The disclosure of security vulnerabilities helps us ensure the security and privacy of our users and their information.
Given the current rolling release pattern for Submitty, any fixed security bugs will only land in the latest version of
Submitty. At this time, we do not plan to backport any fixes to older Submitty versions.

## Guidelines 

We require that all Submitty researchers, developers, and contributors:
* Make every effort to avoid privacy violations, degradation of user experience, disruption to production systems, 
and destruction of data during security testing;
* Perform research only within the scope set out below; 
* Use the identified communication channels to report vulnerability information to us; and
* Keep information about any vulnerabilities you’ve discovered confidential between yourself and Submitty until we’ve 
had 60 days to resolve the issue as well as distribute the resolution to our users.

If you follow these guidelines when reporting an issue to us, we commit to:
* Not pursue or support any legal or academic action related to your research;
* Work with you to understand and resolve the issue quickly (including an initial confirmation of your report 
within 72 hours of submission); 
* Recognize your contribution on our Security Researcher Hall of Fame, if you are the first to report the 
issue and we make a code or configuration change based on the issue.

In the interest of the safety of our users, staff, and you as a security researcher, 
the following test types are excluded from scope: 
* Findings from physical testing such as office access (e.g. open doors, tailgating)
* Findings derived primarily from social engineering (e.g. phishing, vishing)
* Findings from applications or systems not listed in the ‘Scope’ section
* Network level Denial of Service (DoS/DDoS) vulnerabilities

Things we do not want to receive (unless absolutely necessary to replicate the exploit/bug): 
* Personally identifiable information (PII) 
* Grading Information

## Scope 

Testing should be limited only to Submitty and its various components, contained under the 
[Submitty Organization](https://github.com/Submitty).

## How to report a security vulnerability?

If you believe you’ve found a security vulnerability in one of our products or platforms please send it to us 
by emailing [submitty-admin@googlegroups.com](mailto:submitty-admin@googlegroups.com). 

Please include the following details with your report:

* Description of the location and potential impact of the vulnerability;
* A detailed description of the steps required to reproduce the vulnerability (POC scripts, screenshots, and 
compressed screen captures are all helpful to us); and
* Your name/handle and a link for recognition in our Hall of Fame.

## How to receive information on security vulnerabilities?

For any security vulnerability that have been reported to us, once fixed, we promise to email out the fix to
our known sysadmins to allow them to patch their system before we publicly release our fix. To receive information
about this, please send an email to [submitty-admin@googlegroups.com](mailto:submitty-admin@googlegroups.com) with
the subject "Join Submitty Sysadmin List". In this email, please include your name, university email, and link to
your school staff page. We will then add you to the submitty-sysadmin@googlegroups.com mail list.
