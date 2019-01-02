![Sequry](bin/images/Readme.png)

Sequry
========

Sequry is a password manager for teams intended for self-hosting. Sequry allows teams to share passwords like login credentials securely.

Package Name:

    sequry/core


Features
--------

* Manage your passwords, login credentials, secret data (etc.) securely
* Safely share passwords with other Sequry users without the need of unsecure channels like chat or e-mail
* Full control over who sees your passwords
* Fine-grained permission system for adminstrators
* Create Sequry groups that share password permissions
* Modular authentication - Install special Sequry authentication modules that offer Sequry authentication with various data
  * Currently available: `sequry/auth-password` (default), `sequry/auth-secondpassword`, `sequry-keyfile`
* Create and assign Security Classes that determine which authentication modules are allowed for a password
* Share passwords via URL with users outside of your ecosystem (PasswordLinks)
* State-of-the-art cryptography

Installation
------------
The Package Name is: sequry/core

Dependencies
------------

Sequry requires PHP >=7.2 since the cryptograhic library that is used (`sodium`) is part of the PHP core since that version.

```bash
// PHP bcmath
$ sudo apt-get install php-bcmath
```

Contribute
----------
- Project: https://dev.quiqqer.com/sequry/core
- Issue Tracker: https://dev.quiqqer.com/sequry/core/issues
- Source Code: https://dev.quiqqer.com/sequry/core/tree/master

Support
-------
If you have found any errors, have wishes or suggestions for improvement,
you can contact us by email at support@pcsg.de or create an issue in the issue tracker.

We will try to meet your needs or send them to the responsible developers
of the project.

License
-------
GPL-3.0+