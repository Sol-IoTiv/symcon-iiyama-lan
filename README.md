# iiyama LAN für IP-Symcon

Steuert iiyama-Displays über das binäre RS232/LAN-Protokoll per TCP. Das Modul kann den Bildschirm einschalten, in den Standby schalten, den Eingang wechseln, Geräte- und Statuswerte abfragen und Wochenzeitpläne im Display verwalten. Benötigt **IP-Symcon 7.0 oder neuer**.

[Changelog](CHANGELOG.md)

## Kompatible Displays

**Am Gerät getestet** wurde der **iiyama ProLite LH4366UHSB-B1AG** mit Firmware **LU66M79-B11-19** und LAN-Protokoll **v3.00**.

**Weitere LH66-Modelle** sind Kandidaten, wenn iiyama auf ihrer Produktseite dieselbe RS232/LAN-Befehlsliste nennt. Für die LH81-Serie und einzelne LH64-Modelle verweist iiyama ebenfalls auf gemeinsame Protokolldokumentation. Diese Modelle wurden mit dem Modul noch nicht am Gerät getestet. Pro Modell und Firmware können einzelne Funktionen abweichen, besonders Quellen, Wochenzeitplan, Geräteinformationen und Aufwecken aus Standby.

**Andere iiyama-Serien** bitte anhand ihrer Befehlsliste prüfen. Maßgeblich sind der binäre A6-Befehlsrahmen und die konkreten Befehle samt Rückmeldungen. TCP-Port 5000 allein bestätigt keine Kompatibilität. Beginne bei einem neuen Modell mit **Geräteinformationen aktualisieren** und **Status aktualisieren**. Prüfe danach die gewünschte Funktion gezielt.

[iiyama RS232/LAN-Protokoll für LH66 und LH81](https://iiyama.com/f/2745ee2456421dcc5459a549095c2d11_rs232-lan-commands-2026-08-txx29as-lhxx66-lhxx81.pdf) · [iiyama LH8664UHS-B2AG mit Protokolldownload](https://iiyama.com/gl_en/products/prolite-lh8664uhs-b2ag/)

## Bildschirm einrichten

Die folgenden Menünamen beziehen sich auf den LH4366UHSB-B1AG.

1. Den Bildschirm per **Ethernet oder WLAN** mit einem Netz verbinden, das der Symcon-Server erreichen kann. Die IP-Adresse am Bildschirm ablesen. Eine DHCP-Reservierung hält die Adresse für Symcon stabil.
2. Mit der Fernbedienung **Menü → Gerät → RS232-Weiterleitung / RS232 routing** öffnen und **LAN RS232** auswählen. Die LAN-Steuerung verwendet **TCP 5000**.
3. Bei einem einzelnen Display in Symcon zunächst **Monitor-ID 1** verwenden. Falls am Display eine andere Monitor-ID eingestellt ist, diese in Symcon übernehmen.
4. Zuerst bei eingeschaltetem Display testen. Soll das Display über LAN auch aus Standby aufwachen, die Einstellungen für **Energiesparmodus** und **Admin → Konferenzraum** prüfen. Entscheidend ist, dass die Netzwerksteuerung im gewählten Standby erreichbar bleibt. Das Verhalten kann sich bei Ethernet, WLAN und je nach Firmware unterscheiden. Das Modul sendet Einschaltbefehle über TCP und kein Wake-on-LAN-Magic-Packet.
5. Für den Wochenzeitplan Datum, Uhrzeit und Zeitzone am Display kontrollieren.

[iiyama-Handbuch für LH66](https://iiyama.com/f/d20b8feb4feddf498dcc479896838547_lh-43-50-55-65-7566uhsb-usermanual-g.pdf) · [iiyama LAN-Einrichtung und Aufwecken im Protokollhandbuch](https://iiyama.com/f/2745ee2456421dcc5459a549095c2d11_rs232-lan-commands-2026-08-txx29as-lhxx66-lhxx81.pdf#page=8)

## Über die Symcon-Modulverwaltung installieren

1. In der **Symcon-Verwaltungskonsole** die **Logische Baumansicht → Kern-Instanzen → Modules** öffnen.
2. Mit **+** die öffentliche GitHub-URL `https://github.com/Sol-IoTiv/symcon-iiyama-lan.git` hinzufügen. Der Standardzweig ist **main**. Der Abruf benötigt weder einen SSH-Key noch ein GitHub-Konto oder Token.
3. Im Objektbaum **Instanz hinzufügen** wählen und nach **iiyama ProLite LAN** suchen. Für jeden Bildschirm eine eigene Instanz anlegen.
4. Unter **Verbindung zum Bildschirm** die IP-Adresse eintragen. **TCP-Port 5000** und **Monitor-ID 1** sind voreingestellt. Falls gewünscht unter **Automatische Statusabfrage** ein Intervall eintragen, etwa **60 Sekunden**.
5. **Übernehmen** klicken und im Abschnitt **Status und Geräteinformationen** zuerst **Status aktualisieren** und anschließend **Geräteinformationen aktualisieren** anklicken.

Updates findest du in **Modules → Auf Aktualisierung prüfen**. Wenn die Bibliothek bereits über eine andere Quelle geladen wird, behalte die vorhandene Installation bis zum geplanten Quellenwechsel bei. Beide Quellen verwenden dieselbe Bibliotheks- und Modul-ID.

[Symcon-Dokumentation zur Modulverwaltung](https://www.symcon.de/de/service/dokumentation/modulreferenz/kern-instanzen/module-control/)

## Status und Bedienung

Das Abfrageintervall wird in Sekunden angegeben. **0** deaktiviert die automatische Abfrage. **Status aktualisieren** löst dieselbe Abfrage manuell aus. Das Modul fragt zuerst den Betriebszustand ab. Im eingeschalteten Zustand folgen Eingang, Ton, Bildwerte, Betriebsstunden, Farbtemperatur, Bildformat und Pixel Shift. Modell, Firmware und LAN-Protokollversion werden separat über **Geräteinformationen aktualisieren** abgefragt.

Unter **Bildschirm bedienen** stehen **Ein und HDMI 1** und **Standby** bereit. Die Variablen unter der Instanz zeigen die zuletzt erfolgreich gelesenen oder bestätigten Werte. **TCP beim letzten Versuch erreichbar**, **Letzter Fehler** und **Letzter bestätigter Betriebsstatus** helfen bei der Fehlersuche.

## Wochenzeitplan am Bildschirm

1. Den Abschnitt **Wochenzeitplan am Bildschirm** öffnen und einen Platz von **1 bis 7** wählen.
2. **Zeitplan lesen** übernimmt die gespeicherten Ein- und Ausschaltzeiten, den HDMI-Eingang und die Wochentage in die Felder. Unterhalb der Lesetaste erscheint der geladene Zeitplan mit seinem Aktivierungsstatus.
3. Einschaltzeit, Ausschaltzeit, HDMI-Eingang und Wochentage eintragen. Die Einschaltzeit muss am selben Tag vor der Ausschaltzeit liegen.
4. **Zeitplan speichern und aktivieren** klicken. Das Modul schreibt die Daten, aktiviert den Platz und liest beide Schritte zur Kontrolle zurück. Mit **Zeitplan deaktivieren** lässt sich der gewählte Platz abschalten.

Der Bildschirm führt den Zeitplan nach seiner eigenen Uhr aus. Im Modul lassen sich **HDMI 1** und **HDMI 2** für den Zeitplan auswählen.

[iiyama-Protokoll, Abschnitt 9 zum Zeitplan](https://iiyama.com/f/2745ee2456421dcc5459a549095c2d11_rs232-lan-commands-2026-08-txx29as-lhxx66-lhxx81.pdf#page=28)

## Symcon-Skriptbefehle

Der erste Parameter ist jeweils die Instanz-ID. Die Wochentage werden als Maske übergeben. **Mo = 2**, **Di = 4**, **Mi = 8**, **Do = 16**, **Fr = 32**, **Sa = 64**, **So = 128**. Für Montag bis Freitag ergibt das **62**.

~~~php
IIY_Aktualisieren(12345);
IIY_GeraeteinformationenAktualisieren(12345);
IIY_Einschalten(12345);
IIY_Standby(12345);
IIY_EinHDMI1(12345);
IIY_EingangSetzen(12345, 6); // HDMI 2
IIY_LautstaerkeSetzen(12345, 20);
IIY_AudioausgangSetzen(12345, 20);
IIY_HelligkeitSetzen(12345, 30);
echo IIY_Kaltstart(12345); // 0 = aus, 1 = einschalten, 2 = letzter Zustand
echo IIY_ZeitplanLesen(12345, 1);
IIY_ZeitplanSetzen(12345, 1, '07:00', '20:00', 13, 62); // HDMI 1, Mo–Fr
IIY_ZeitplanDeaktivieren(12345, 1);
echo IIY_HexSenden(12345, 'A6 01 00 00 00 03 01 19 BC', 0x19);
~~~

Bei Hex-Befehlen gibt der dritte Parameter den erwarteten Antwortcode an. **0** wartet auf die Bestätigung eines Set-Befehls, **-1** nimmt die erste gültige Antwort. Der Hex-Rahmen muss die Monitor-ID der Instanz oder die Broadcast-ID **0** und eine gültige Prüfsumme enthalten. Bei Broadcast wird keine Antwort erwartet.

## PDF-Wiedergabe und FTP

FTP lässt sich am Display unter **System → Android-Einstellung → Anzeige der Beschilderung → Server-Einstellungen → FTP** aktivieren. Dort stehen Konto, Passwort, Speicherpfad und FTP-Port. Den Speicherort und die Wiedergabeliste des PDF-Players stellst du unter **Anzeige der Beschilderung → Quelleneinstellungen** ein. Der FTP-Schalter ist in der LAN-Befehlsliste nicht dokumentiert. Das Modul steuert den Eingang. Auf Displays mit eingebautem PDF-Player und der oben verlinkten Quellenbelegung ist der Aufruf `IIY_EingangSetzen(12345, 0x17)`.

[iiyama-Handbuch, FTP und Quelleneinstellungen](https://iiyama.com/f/d20b8feb4feddf498dcc479896838547_lh-43-50-55-65-7566uhsb-usermanual-g.pdf#page=46)
