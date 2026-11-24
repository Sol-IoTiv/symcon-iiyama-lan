# Changelog

## 1.0 · Build 9 · 2026-11-24

- **Zeitplan lesen** übernimmt Uhrzeiten, HDMI-Eingang und Wochentage aus dem gewählten Zeitplan direkt in die Instanzmaske.

## 1.0 · Build 8 · 2026-11-24

- Den Button **Changelog öffnen** aus der Instanzkonfiguration entfernt. Der Änderungsverlauf ist im README verlinkt.

## 1.0 · Build 7 · 2026-11-24

- Die Instanz zeigt unter **Verbindung zum Bildschirm** den Menüpfad zum Aktivieren der LAN-Steuerung an.

## 1.0 · Build 6 · 2026-11-24

- Der Link **Dokumentation** in der Symcon-Instanz öffnet die Einrichtungsanleitung des Moduls auf GitHub.
- Der Änderungsverlauf ist über **Changelog öffnen** in der Instanz und über das README erreichbar.

## 1.0 · Build 5 · 2026-11-24

- Erste öffentliche Veröffentlichung. Symcon kann das Repository per HTTPS ohne GitHub-Zugangsdaten abrufen.
- Instanzmaske in Verbindung, automatische Statusabfrage, Status und Geräteinformationen, manuelle Bedienung und Wochenzeitplan gegliedert.
- Die IP-Adresse wird bei neuen Instanzen eingetragen.
- Anleitung für die Display-Einrichtung, Installation über Modules und Modellkompatibilität ergänzt.

## 1.0 · Build 4 · 2026-11-24

- Konfigurierbare automatische Statusabfrage in Sekunden hinzugefügt.
- Statuswerte um Kontrast, Schärfe, Farbtemperatur, Bildformat und Pixel Shift ergänzt.
- Separate Abfrage von Gerätemodell, Firmware und LAN-Protokollversion hinzugefügt.
- Zeitpunkt des letzten bestätigten Betriebsstatus als Variable ergänzt.

## 1.0 · Build 3 · 2026-11-24

- Zeitplaneinstellungen am Display zuerst speichern und anschließend separat aktivieren. Beide Schritte werden zurückgelesen.
- Bei einem Fehler versucht das Modul, den vorherigen Zeitplan wiederherzustellen.

## 1.0 · Build 2 · 2026-11-24

- Die Variable für TCP-Erreichbarkeit funktioniert auch auf Symcon-Installationen ohne das Profil `~YesNo`.

## 1.0 · Build 1 · 2026-11-24

- Erste interne Version für den iiyama ProLite LH4366UHSB-B1AG.
- LAN-Steuerung für Einschalten, Standby, Eingangswechsel, Lautstärke und Helligkeit.
- Manuelle Statusabfrage, Verwaltung der Zeitpläne im Display und Skriptbefehle einschließlich Hex-Kommandos.
