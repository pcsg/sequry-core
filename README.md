Passwort-Manager für Gruppen und Benutzer
========



Paketname:

    pcsg/grouppasswordmanager


Features (Funktionen)
--------

Passwort-Manager zur Verwaltung von Passwörtern bzw. geheimen Daten:

* Jeder Benutzer kann Passwörter erstellen und diese mit (geheimen) Daten füllen
* Passwörter werden verschlüsselt abgespeichert
* Passwörter können mit anderen Benutzern und Gruppen geteilt werden, so dass auch andere Benutzer diese Passwörter einsehen können
* Der Zugriff erfolgt über konfigurierte Authentifizierungs-Module, welche eigene QUIQQER Module sind
  * z.B.: pcsg/gpmauthpassword für die Authentifizierung über das QUIQQER Login-Passwort
    

Installation
------------

Der Paketname ist: pcsg/grouppasswordmanager


Abhängigkeiten
------------

* PHP >= 7.0
* PHP-Modul `bcmath` -> `sudo apt-get install php-bcmath`
* libsodium (**Reihenfolge beachten!**) [s. auch: https://paragonie.com/book/pecl-libsodium/read/00-intro.md#installing-libsodium]
  1. `sudo apt-get install php-dev`
  2. libsodium library installieren: Es wird **mindestens** Version `1.0.9` vorausgesetzt!
    * Bei Ubuntu >= `16.10`: `sudo apt-get install libsodium-dev`
    * Bei Ubuntu <= `16.04` muss selbst kompiliert werden:
        * `sudo apt-get install build-essential`
        * `git clone -b stable https://github.com/jedisct1/libsodium.git`
        * `cd libsodium`
        * `sudo ./configure && make check && make install`
        * `cd /usr/lib/x86_64-linux-gnu/`
        * Wenn der symlink `libsodium.so` schon existiert: `sudo rm libsodium.so`
        * Wenn der symlink `libsodium.so.18` schon existiert: `sudo rm libsodium.so.18`
        * `sudo ln -s /usr/local/lib/libsodium.so libsodium.so`
        * `sudo ln -s /usr/local/lib/libsodium.so libsodium.so.18`
        * `sudo pecl install libsodium`

Mitwirken
----------

- Issue Tracker:    https://dev.quiqqer.com/pcsg/grouppasswordmanager/issues
- Source Code:      https://dev.quiqqer.com/pcsg/grouppasswordmanager/tree/dev


Support
-------

Falls Sie ein Fehler gefunden haben, oder Verbesserungen wünschen,
schreiben Sie eine E-Mail mit einer genauen Beschreibung an p.mueller@pcsg.de.


Lizenz
-------


Entwickler
--------

Patrick Müller (www.pcsg.de)