# Speedtest.net for PHP

Library and command line interface to run server-side speedtests, from cli or web interface.

This library is an updated version of [aln-1/speedtest-php](https://github.com/aln-1/speedtest-php) library. Original author is @aln-1.

[![Latest Version](https://poser.pugx.org/nextpost-tech/speedtest-php/v)](//packagist.org/packages/nextpost-tech/speedtest-php)
[![Total Downloads](https://poser.pugx.org/nextpost-tech/speedtest-php/downloads)](//packagist.org/packages/nextpost-tech/speedtest-php)
[![License](https://poser.pugx.org/nextpost-tech/speedtest-php/license)](//packagist.org/packages/nextpost-tech/speedtest-php)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D%207.0-blue)](//packagist.org/packages/nextpost-tech/speedtest-php)

# Navigation

- [Installation](#installation)
    - [How to install library **to existing project** using Composer](#how-to-install-library-to-existing-project-using-composer)
    - [How to install library **to new project** using Composer](#how-to-install-library-to-new-project-using-composer)
- [Update](#update)
- [Examples](#examples)
    - [Regular Usage](#regular-usage)
    - [CLI Usage](#cli-usage)
- [Support](#support)
- [Paid Consultations & Personal Help](#paid-consultations--personal-help)

# Installation

## How to install library **to existing project** using Composer

Since, our repository are public package we listed this repository on Packagist, that's mean we don't need to instruct Composer about which GitHub repository to look for inorder to find the package.

### As project dependency

- Install library using the following commands:

```console
cd /path-to-app/
composer require nextpost-tech/speedtest-php
```

### By updating `composer.json` file of your project

- Open file named as `composer.json` in project folder `/path-to-app/`

- Add following content to this file:

```php
{
    "require": {
        // ---
        // Other packages
        // ---
        "nextpost-tech/speedtest-php": "^1.0"
    }
}
```

- After that install library using the following commands:

```console
cd /path-to-app/
composer update
```

- `/path-to-app/` project folder with existing `composer.json` file 

## How to install library **to new project** using Composer

Use following command, when you need to install library **to new project** using Composer:

- If Composer not installed on your server/local machine, please follow this [installation guide](https://www.digitalocean.com/community/tutorials/how-to-install-and-use-composer-on-ubuntu-20-04)

- Create blank file named as `composer.json` in project folder

- Add following content to this file:

```php
{
    "require": {
        "nextpost-tech/speedtest-php": "^1.0"
    }
}
```

- After that install library using the following command:

```console
cd /path-to-app/
composer update
```

- `/path-to-app/` project folder with existing `composer.json` file

# Update

Use following command, when you need to update library to latest version:

```php
cd /path-to-app/
composer update
```

- `/path-to-app/` project folder with existing `composer.json` file

# Examples

## Regular Usage

- Minimal code

```php
require 'vendor/autoload.php';

$speedtest = new NextpostTech\Speedtest\Speedtest();
$speedtest->getServers();
$speedtest->getBestServer();
$speedtest->download();
$speedtest->upload();

$results = $speedtest->results();

print_r($results);
```

```console
NextpostTech\Speedtest\Result Object
(
    [latency:protected] => 4.57
    [download:protected] => 47888585.578516
    [upload:protected] => 64841042.860629
    [bytesReceived:protected] => 59881235
    [bytesSent:protected] => 82579808
)
```

## CLI Usage

# Support

This is a developer's portal for Speedtest.net for PHP and should not be used for support. Please [contact us via website chat](https://nextpost.tech/#chatraChatExpanded) if you need to submit a support request.

# Paid Consultations & Personal Help

Please [contact us via website chat](https://nextpost.tech/#chatraChatExpanded) if you need additional help with your own project, we will try to find best options for you.


