SilverStripe Encrypt module
==================
[![Build Status](https://travis-ci.org/lekoala/silverstripe-encrypt.svg?branch=master)](https://travis-ci.org/lekoala/silverstripe-encrypt)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/lekoala/silverstripe-encrypt/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/lekoala/silverstripe-encrypt/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/lekoala/silverstripe-encrypt/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/lekoala/silverstripe-encrypt/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/lekoala/silverstripe-encrypt/badges/build.png?b=master)](https://scrutinizer-ci.com/g/lekoala/silverstripe-encrypt/build-status/master)
[![codecov.io](https://codecov.io/github/lekoala/silverstripe-encrypt/coverage.svg?branch=master)](https://codecov.io/github/lekoala/silverstripe-encrypt?branch=master)

[![Latest Stable Version](https://poser.pugx.org/lekoala/silverstripe-encrypt/version)](https://packagist.org/packages/lekoala/silverstripe-encrypt)
[![Latest Unstable Version](https://poser.pugx.org/lekoala/silverstripe-encrypt/v/unstable)](//packagist.org/packages/lekoala/silverstripe-encrypt)
[![Total Downloads](https://poser.pugx.org/lekoala/silverstripe-encrypt/downloads)](https://packagist.org/packages/lekoala/silverstripe-encrypt)
[![License](https://poser.pugx.org/lekoala/silverstripe-encrypt/license)](https://packagist.org/packages/lekoala/silverstripe-encrypt)
[![Monthly Downloads](https://poser.pugx.org/lekoala/silverstripe-encrypt/d/monthly)](https://packagist.org/packages/lekoala/silverstripe-encrypt)
[![Daily Downloads](https://poser.pugx.org/lekoala/silverstripe-encrypt/d/daily)](https://packagist.org/packages/lekoala/silverstripe-encrypt)

[![Dependency Status](https://www.versioneye.com/php/lekoala:silverstripe-encrypt/badge.svg)](https://www.versioneye.com/php/lekoala:silverstripe-encrypt)
[![Reference Status](https://www.versioneye.com/php/lekoala:silverstripe-encrypt/reference_badge.svg?style=flat)](https://www.versioneye.com/php/lekoala:silverstripe-encrypt/references)

![codecov.io](https://codecov.io/github/lekoala/silverstripe-encrypt/branch.svg?branch=master)


Easily add encryption to your DataObjects

Currently, only Text and HTMLText are supported. Simply replace your text fields by their
corresponding DBEncryptedText or DBEncryptedHTMLText.

TODO:
- Add easily searchable encrypted text

Note: version for SilverStripe 4 coming soon!

Security
==================

Make sure that the private key is not accessible! The key will be generated by default one level above your baseFolder. 
This can be altered in the encrypt.yml configuration file.  Note that if you are using SilverStripe 4, you will need
to change this as it lacks a '/public' directory.

You can also define the key in an overriding config file, as below.

```yml
---
Name: sharedencryptionkey
After:
  - '#silverstripeencrypt'
---

LeKoala\SilverStripeEncrypt\EncryptHelper:
   encrypted_shared_key: YOURSHAREDKEY---

```


Compatibility
==================
Unit tested with 4.1 and 4.2

Maintainer
==================
LeKoala - thomas@lekoala.be
