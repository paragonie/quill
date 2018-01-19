# Quill

[![Build Status](https://travis-ci.org/paragonie/quill.svg?branch=master)](https://travis-ci.org/paragonie/quill)
[![Latest Stable Version](https://poser.pugx.org/paragonie/quill/v/stable)](https://packagist.org/packages/paragonie/quill)
[![Latest Unstable Version](https://poser.pugx.org/paragonie/quill/v/unstable)](https://packagist.org/packages/paragonie/quill)
[![License](https://poser.pugx.org/paragonie/quill/license)](https://packagist.org/packages/paragonie/quill)
[![Downloads](https://img.shields.io/packagist/dt/paragonie/quill.svg)](https://packagist.org/packages/paragonie/quill)

Quill is a library for publishing data to a [Chronicle](https://github.com/paragonie/chronicle) instance.
**Requires PHP 7 or newer. PHP 7.2+ is recommended.**

A [monolog handler](https://github.com/paragonie/monolog-quill) is also available. 

## Installing

```sh
composer require paragonie/quill
```

## Usage

```php
<?php

use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Quill\Quill;
use ParagonIE\Sapient\CryptographyKeys\{
    SigningSecretKey,
    SigningPublicKey
};

$quill = (new Quill())
    ->setChronicleURL('https://chronicle-public-test.paragonie.com/chronicle')
    ->setServerPublicKey(
        new SigningPublicKey(
            Base64UrlSafe::decode('3BK4hOYTWJbLV5QdqS-DFKEYOMKd-G5M9BvfbqG1ICI=')
        )
    )
    ->setClientID('**Your Client ID provided by the Chronicle here**')
    ->setClientSecretKey(
        new SigningSecretKey('/* Loaded from the filesystem or something. */')
    );

$quill->write("Important security notice goes here.");
```

### Writing Data (Unencrypted)

There are two main API methods that do the same thing but differ in their return
values:

* `write(string $input): ResponseInterface`
  * Returns the PSR-7 Response object, or throws an exception
* `blindWrite(string $input): bool`
  * Returns `TRUE` or `FALSE`

### Writing Data (Symmetric Encryption)

If you want to encrypt your messages using a [shared encryption key](https://github.com/paragonie/sapient/blob/master/docs/Internals/CryptographyKey.md):

* `writeEncrypted(string $input, SharedEncryptionKey $key): ResponseInterface`
  * Returns the PSR-7 Response object, or throws an exception
* `blindWriteEncrypted(string $input, SharedEncryptionKey $key): bool`
  * Returns `TRUE` or `FALSE`

### Writing Data (Asymmetric Encryption)
  
If you want to encrypt your messages using a [public-key cryptography](https://github.com/paragonie/sapient/blob/master/docs/Internals/CryptographyKey.md):

* `writeSealed(string $input, SealingPublicKey $key): ResponseInterface`
  * Returns the PSR-7 Response object, or throws an exception
* `blindWriteSealed(string $input, SealingPublicKey $key): bool`
  * Returns `TRUE` or `FALSE`

