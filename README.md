Elephant.io
===========
[![Build Status](https://travis-ci.org/Wisembly/elephant.io.svg?branch=master)](https://travis-ci.org/Wisembly/elephant.io)
[![Latest Stable Version](https://poser.pugx.org/wisembly/elephant.io/v/stable.svg)](https://packagist.org/packages/wisembly/elephant.io)
[![Total Downloads](https://poser.pugx.org/wisembly/elephant.io/downloads.svg)](https://packagist.org/packages/wisembly/elephant.io) 
[![License](https://poser.pugx.org/wisembly/elephant.io/license.svg)](https://packagist.org/packages/wisembly/elephant.io)

```
        ___     _,.--.,_         Elephant.io is a rough websocket client
      .-~   ~--"~-.   ._ "-.     written in PHP. Its goal is to ease the
     /      ./_    Y    "-. \    communications between your PHP Application and
    Y       :~     !         Y   a real-time server.
    lq p    |     /         .|
 _   \. .-, l    /          |j   Requires PHP 5.4 and openssl, licensed under
()\___) |/   \_/";          !    the MIT License.
 \._____.-~\  .  ~\.      ./
            Y_ Y_. "vr"~  T      Built-in Engines :
            (  (    |L    j      - Socket.io 1.x
            [nn[nn..][nn..]      - Socket.io 0.x (courtesy of @kbu1564)
          ~~~~~~~~~~~~~~~~~~~
```

NOTICE
======
As we are not using Elephant.io anymore at Wisembly, and not having the time to
maintain this library, we are looking for maintainers. Please look at the dedicated
issue #135 !

Installation
============
You have multiple ways to install Elephant.io. If you are unsure what to do, go with
[the archive release](#archive-release).

Once the library is downloaded and extracted wherever you want it to be, it
should be loaded through a [PSR-4](http://www.php-fig.org/psr/psr-4/) autoload
mecanism, with a `Wisembly\ElepehantIO` prefix. This is not necessary with the
composer way, as it is handling this part by itself.

Archive Release
---------------
1. Download the most recent release from the [release page](https://github.com/Wisembly/elephant.io/releases)
2. Unpack the archive
3. Move the files somewhere in your project

Development version
-------------------
1. Install Git
2. `git clone git://github.com/Wisembly/elephant.io.git`

Via Composer
------------
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

Documentation
=============
The docs are not written yet, but you should check [the example directory](https://github.com/Wisembly/elephant.io/tree/master/example)
to have a basic knownledge on how this library is meant to work.

Special Thanks
==============
Special thanks goes to Mark Karpeles who helped the project founder to understand the way websockets works.
