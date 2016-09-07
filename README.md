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

* PHP >= 5.6
* libsodium (**Reihenfolge beachten!**) [s. auch: https://paragonie.com/book/pecl-libsodium/read/00-intro.md#installing-libsodium]
  1. `sudo apt-get install php-dev`
  2. `sudo apt-get install libsodium-dev`
  3. `sudo pecl install libsodium`
* scrypt (`sudo pecl install scrypt`) [s. auch: https://github.com/DomBlack/php-scrypt]

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