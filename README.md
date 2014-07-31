# Elephant.io
[![Build Status](https://travis-ci.org/Wisembly/elephant.io.png?branch=master)](https://travis-ci.org/Wisembly/elephant.io)

MIT Licenced - Copyright © 2012. Wisembly

## About
Elephant.io is a rough websocket client written in PHP. Its goal is to ease 
communications between your PHP application and a real time server using the
websocket transport protocol.

## Licence
This software is distributed under MIT Licence. See LICENCE for more info.

## Requirements
You'll need PHP 5.4 (or more) configured with openssl.

Installation
============
You have multiple ways to install Elephant.io. If you are unsure what to do, go with
[the archive release](#archive-release).

### Archive Release
1. Download the most recent release from the [release page](https://github.com/Wisembly/elephant.io/releases)
2. Unpack the archive
3. Move the files somewhere in your project

### Development version
1. Install Git
2. `git clone git://github.com/Wisembly/elephant.io.git`

### Via Composer
1. Install composer in your project: `curl -s http://getcomposer.org/installer | php`
2. Create a `composer.json` file (or update it) in your project root:

    ```javascript

      {
        "require": {
          "wisembly/elephant.io": "~3.0"
        }
      }
    ```

3. Install via composer : `php composer.phar install`


## Special Thanks
Special thanks goes to Mark Karpeles who helped the project founder to understand the way websockets works.
