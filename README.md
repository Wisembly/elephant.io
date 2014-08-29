Elephant.io
===========
[![Build Status](https://travis-ci.org/Wisembly/elephant.io.png?branch=master)](https://travis-ci.org/Wisembly/elephant.io)

```
        ___     _,.--.,_         Elephant.io is a rought websocket client
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

The docs are not written yet, but you should check [the example directory](https://github.com/Wisembly/elephant.io/tree/master/example)
to have a basic knownledge on how this library is meant to work.

Installation
============
You have multiple ways to install Elephant.io. If you are unsure what to do, go with
[the archive release](#archive-release).

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

Special Thanks
==============
Special thanks goes to Mark Karpeles who helped the project founder to understand the way websockets works.
