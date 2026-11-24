<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/IiyamaLanProtocol.php';

class IiyamaLAN extends IPSModule
{
    private const SOURCES = [13 => 'HDMI 1', 6 => 'HDMI 2'];
    private const SCHEDULE_DAYS = [
        'PlanMontag' => 'Mo',
        'PlanDienstag' => 'Di',
        'PlanMittwoch' => 'Mi',
        'PlanDonnerstag' => 'Do',
        'PlanFreitag' => 'Fr',
        'PlanSamstag' => 'Sa',
        'PlanSonntag' => 'So'
    ];
    private const PICTURE_FORMATS = [0 => '4:3', 1 => 'Benutzerdefiniert', 2 => '1:1', 3 => 'Vollbild', 4 => '21:9', 5 => 'Dynamisch', 6 => '16:9'];
    private const COLOR_TEMPERATURES = [
        0 => 'Benutzer 1', 1 => 'Nativ', 2 => '11000 K', 3 => '10000 K', 4 => '9300 K',
        5 => '7500 K', 6 => '6500 K', 7 => '5770 K', 8 => '5500 K', 9 => '5000 K', 10 => '4000 K',
        11 => '3400 K', 12 => '3350 K', 13 => '3000 K', 14 => '2800 K', 15 => '2600 K',
        16 => '1850 K', 18 => 'Benutzer 2'
    ];

    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyString('Adresse', '');
        $this->RegisterPropertyInteger('Port', 5000);
        $this->RegisterPropertyInteger('MonitorID', 1);
        $this->RegisterPropertyInteger('Abfrageintervall', 0);
        $this->RegisterPropertyInteger('UsbCQuellcode', 0);
        $this->RegisterPropertyInteger('DateimanagerQuellcode', 0);
        $this->RegisterTimer('Statusabfrage', 0, 'IIY_TimerAbfragen($_IPS["TARGET"]);');
        $profile = 'IiyamaLAN.Eingang';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
            foreach (self::SOURCES as $code => $caption) {
                IPS_SetVariableProfileAssociation($profile, $code, $caption, '', -1);
            }
        }
        $this->RegisterVariableBoolean('Power', 'Eingeschaltet', '~Switch', 10);
        $this->RegisterVariableInteger('Eingang', 'Eingang', $profile, 20);
        $this->RegisterVariableInteger('Lautstaerke', 'Lautstärke', '~Intensity.100', 30);
        $this->RegisterVariableInteger('Helligkeit', 'Helligkeit', '~Intensity.100', 40);
        $this->RegisterVariableInteger('Audioausgang', 'Audioausgang', '~Intensity.100', 50);
        $this->RegisterVariableInteger('Betriebsstunden', 'Betriebsstunden', '', 60);
        $this->RegisterVariableBoolean('Erreichbar', 'TCP beim letzten Versuch erreichbar', '', 70);
        $this->RegisterVariableString('LetzterFehler', 'Letzter Fehler', '', 80);
        $this->RegisterVariableInteger('LetzterStatus', 'Letzter bestätigter Betriebsstatus', '~UnixTimestamp', 90);
        $this->RegisterVariableInteger('Kontrast', 'Kontrast', '~Intensity.100', 100);
        $this->RegisterVariableInteger('Schaerfe', 'Schärfe', '~Intensity.100', 110);
        $this->RegisterVariableString('Farbtemperatur', 'Farbtemperatur', '', 120);
        $this->RegisterVariableString('Bildformat', 'Bildformat', '', 130);
        $this->RegisterVariableString('PixelShift', 'Pixel Shift', '', 140);
        $this->RegisterVariableString('Modell', 'Gerätemodell', '', 150);
        $this->RegisterVariableString('Firmware', 'Firmware', '', 160);
        $this->RegisterVariableString('Protokollversion', 'LAN-Protokollversion', '', 170);
        foreach (['Power', 'Eingang', 'Lautstaerke', 'Audioausgang', 'Helligkeit'] as $ident) {
            $this->EnableAction($ident);
        }
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $valid = $this->validConfiguration();
        $seconds = $this->ReadPropertyInteger('Abfrageintervall');
        $this->SetTimerInterval('Statusabfrage', $valid && $seconds >= 1 && $seconds <= 86400 ? 1000 * $seconds : 0);
        $this->SetStatus($valid ? 102 : 201);
    }

    public function GetConfigurationForm(): string
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true, 512, JSON_THROW_ON_ERROR);
        $fields = [
            'UsbCButton' => $this->sourceButton($this->ReadPropertyInteger('UsbCQuellcode')),
            'DateimanagerButton' => $this->sourceButton($this->ReadPropertyInteger('DateimanagerQuellcode'))
        ];
        if ($this->validConfiguration()) {
            try {
                $fields += $this->scheduleFormFields(1);
            } catch (RuntimeException $error) {
                $fields['PlanStatus'] = [
                    'caption' => 'Zeitplan 1 konnte nicht geladen werden. ' . $error->getMessage(),
                    'visible' => true
                ];
            }
        }
        $this->fillFormFields($form['actions'], $fields);
        return json_encode($form, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public function RequestAction($Ident, $Value): void
    {
        match ($Ident) {
            'Power' => $Value ? $this->Einschalten() : $this->Standby(),
            'Eingang' => $this->EingangSetzen((int) $Value),
            'Lautstaerke' => $this->LautstaerkeSetzen((int) $Value),
            'Audioausgang' => $this->AudioausgangSetzen((int) $Value),
            'Helligkeit' => $this->HelligkeitSetzen((int) $Value),
            default => throw new InvalidArgumentException('Unbekannte Aktion: ' . $Ident)
        };
    }

    public function Aktualisieren(): void
    {
        $this->execute(fn() => $this->readStatus());
    }

    public function TimerAbfragen(): void
    {
        if ($this->ReadPropertyInteger('Abfrageintervall') <= 0) {
            return;
        }
        $this->execute(fn() => $this->readStatus(), true);
    }

    public function GeraeteinformationenAktualisieren(): void
    {
        $this->execute(function (): void {
            $this->SetValue('Modell', $this->queryText(0xA1, 0));
            $this->SetValue('Firmware', $this->queryText(0xA1, 1));
            $this->SetValue('Protokollversion', $this->queryText(0xA2, 0));
        });
    }

    public function Einschalten(): void
    {
        $this->execute(fn() => $this->setPower(true));
    }

    public function Standby(): void
    {
        $this->execute(function (): void {
            // Beim erneuten Aus-Befehl im Standby wurde einmal ein Aufwachen beobachtet.
            if ($this->readPower()) {
                $this->setPower(false);
            }
        });
    }

    public function EinHDMI1(): void
    {
        $this->powerOnInput(13);
    }

    public function EinHDMI2(): void
    {
        $this->powerOnInput(6);
    }

    public function EingangSetzen(int $Quelle): void
    {
        if ($Quelle < 1 || $Quelle > 255) {
            throw new InvalidArgumentException('Der Quellcode muss ein Byte zwischen 1 und 255 sein.');
        }
        $this->execute(fn() => $this->setInput($Quelle));
    }

    public function QuellcodeLesen(): string
    {
        $source = $this->execute(fn(): int => $this->readInput());
        return sprintf('Aktueller Quellcode %d (0x%02X)', $source, $source);
    }

    public function LautstaerkeSetzen(int $Wert): void
    {
        $this->setVolume($Wert, 0);
    }

    public function AudioausgangSetzen(int $Wert): void
    {
        $this->setVolume($Wert, 1);
    }

    public function HelligkeitSetzen(int $Wert): void
    {
        $this->validatePercent($Wert);
        $this->execute(function () use ($Wert): void {
            $video = $this->query(0x33, 7);
            $video[0] = $Wert;
            $this->command(0, 0x32, ...$video);
            $this->SetValue('Helligkeit', $Wert);
        });
    }

    public function Kaltstart(): int
    {
        return $this->execute(fn(): int => $this->query(0xA4, 1)[0]);
    }

    public function ZeitplanLesen(int $Nummer): string
    {
        return $this->formatSchedule($Nummer, $this->readScheduleDetails($Nummer));
    }

    public function ZeitplanFormularLaden(int $Nummer): void
    {
        foreach ($this->scheduleFormFields($Nummer) as $field => $parameters) {
            foreach ($parameters as $parameter => $value) {
                $this->UpdateFormField($field, $parameter, $value);
            }
        }
    }

    public function ZeitplanSetzen(int $Nummer, string $Ein, string $Aus, int $Quelle, int $Tagesmaske): void
    {
        $this->validateSlot($Nummer);
        [$startH, $startM] = $this->parseTime($Ein);
        [$endH, $endM] = $this->parseTime($Aus);
        if ($startH * 60 + $startM >= $endH * 60 + $endM) {
            throw new InvalidArgumentException('Die Einschaltzeit muss vor der Ausschaltzeit desselben Tages liegen.');
        }
        if (!isset(self::SOURCES[$Quelle]) || $Tagesmaske < 2 || $Tagesmaske > 254 || ($Tagesmaske & 1)) {
            throw new InvalidArgumentException('HDMI 1 oder HDMI 2 und mindestens einen Wochentag auswählen.');
        }
        $desired = [1, $startH, $startM, $endH, $endM, $Quelle, $Tagesmaske | 1, 0];
        $this->execute(function () use ($Nummer, $desired): void {
            $previous = $this->readSchedule($Nummer);
            $inactive = $desired;
            $inactive[0] = 0;
            try {
                // Der LH66 übernimmt die Schaltzeiten beim separaten Aktivieren.
                $this->writeSchedule($Nummer, $inactive);
                $this->writeSchedule($Nummer, $desired);
            } catch (Throwable $failure) {
                try {
                    if ($this->readSchedule($Nummer) !== $previous) {
                        $oldInactive = $previous;
                        $oldInactive[0] = 0;
                        $this->writeSchedule($Nummer, $oldInactive);
                        if ($previous[0] === 1) {
                            $this->writeSchedule($Nummer, $previous);
                        }
                    }
                } catch (Throwable $restoreFailure) {
                    throw new RuntimeException(
                        $failure->getMessage() . ' Vorheriger Zeitplan konnte nicht wiederhergestellt werden. '
                        . $restoreFailure->getMessage(),
                        0,
                        $failure
                    );
                }
                throw $failure;
            }
        });
    }

    public function ZeitplanDeaktivieren(int $Nummer): void
    {
        $this->execute(function () use ($Nummer): void {
            $schedule = $this->readSchedule($Nummer);
            if ($schedule[0] !== 0) {
                $schedule[0] = 0;
                $this->writeSchedule($Nummer, $schedule);
            }
        });
    }

    public function HexSenden(string $Hex, int $Antwortcode): string
    {
        $frame = IiyamaLanProtocol::fromHex($Hex);
        if ($Antwortcode < -1 || $Antwortcode > 255) {
            throw new InvalidArgumentException('Der Antwortcode muss -1 oder ein Byte sein.');
        }
        if (!in_array(ord($frame[1]), [0, $this->ReadPropertyInteger('MonitorID')], true)) {
            throw new InvalidArgumentException('Monitor-ID im Rahmen passt nicht zur Instanz.');
        }
        return $this->execute(fn(): string => strtoupper(implode(' ', str_split(bin2hex($this->exchange($frame, $Antwortcode)), 2))));
    }

    private function setPower(bool $on): void
    {
        $this->command(0, 0x18, $on ? 2 : 1);
        $this->SetValue('Power', $on);
    }

    private function readPower(): bool
    {
        $state = $this->query(0x19, 1)[0];
        if ($state !== 1 && $state !== 2) {
            throw new RuntimeException('Unerwarteter Betriebszustand.');
        }
        $this->SetValue('Power', $state === 2);
        $this->SetValue('LetzterStatus', time());
        return $state === 2;
    }

    private function readStatus(): void
    {
        if (!$this->readPower()) {
            return;
        }
        $this->readInput();
        $this->readVolume();
        $video = $this->query(0x33, 7);
        $this->SetValue('Helligkeit', $video[0]);
        $this->SetValue('Kontrast', $video[2]);
        $this->SetValue('Schaerfe', $video[3]);
        [$high, $low] = $this->query(0x0F, 2, 0x02);
        $this->SetValue('Betriebsstunden', ($high << 8) | $low);

        $color = $this->query(0x35, 1)[0];
        $picture = $this->query(0x3B, 1)[0] & 0x0F;
        $shift = $this->query(0xB1, 1)[0];
        $this->SetValue('Farbtemperatur', self::COLOR_TEMPERATURES[$color] ?? sprintf('Code 0x%02X', $color));
        $this->SetValue('Bildformat', self::PICTURE_FORMATS[$picture] ?? sprintf('Code 0x%02X', $picture));
        $this->SetValue('PixelShift', match (true) {
            $shift === 0 => 'Aus',
            $shift === 0x5B => 'Auto',
            $shift <= 0x5A => ($shift * 10) . ' s',
            default => sprintf('Code 0x%02X', $shift)
        });
    }

    private function powerOnInput(int $source): void
    {
        $this->execute(function () use ($source): void {
            $this->setPower(true);
            $this->setInput($source);
        });
    }

    private function readInput(): int
    {
        $source = $this->query(0xAD, 1)[0];
        $this->SetValue('Eingang', $source);
        return $source;
    }

    private function setInput(int $source): void
    {
        $this->command(0, 0xAC, $source, 0, 0, 0);
        $this->SetValue('Eingang', $source);
    }

    private function setVolume(int $percent, int $channel): void
    {
        $this->validatePercent($percent);
        $this->execute(function () use ($percent, $channel): void {
            $values = $this->readVolume();
            $values[$channel] = $percent;
            $this->command(0, 0x44, ...$values);
            $this->SetValue($channel === 0 ? 'Lautstaerke' : 'Audioausgang', $percent);
        });
    }

    private function readVolume(): array
    {
        $values = $this->query(0x45, 2);
        $this->SetValue('Lautstaerke', $values[0]);
        $this->SetValue('Audioausgang', $values[1]);
        return $values;
    }

    private function readSchedule(int $slot): array
    {
        $this->validateSlot($slot);
        return $this->query(0x5B, 8, $slot);
    }

    private function readScheduleDetails(int $slot): array
    {
        [$enabled, $startH, $startM, $endH, $endM, $source, $mask] = $this->execute(fn(): array => $this->readSchedule($slot));
        $days = [];
        foreach (array_keys(self::SCHEDULE_DAYS) as $index => $field) {
            $days[$field] = ($mask & (1 << ($index + 1))) !== 0;
        }

        return [
            'enabled' => $enabled !== 0,
            'start' => $startH < 24 && $startM < 60 ? sprintf('%02d:%02d', $startH, $startM) : '',
            'end' => $endH < 24 && $endM < 60 ? sprintf('%02d:%02d', $endH, $endM) : '',
            'source' => $source,
            'days' => $days,
            'weekly' => ($mask & 1) !== 0
        ];
    }

    private function formatSchedule(int $slot, array $schedule): string
    {
        $days = array_intersect_key(self::SCHEDULE_DAYS, array_filter($schedule['days']));
        return sprintf(
            'Zeitplan %d – %s | Ein %s | Aus %s | %s | %s | %s',
            $slot,
            $schedule['enabled'] ? 'aktiv' : 'deaktiviert',
            $schedule['start'] === '' ? '–' : $schedule['start'],
            $schedule['end'] === '' ? '–' : $schedule['end'],
            self::SOURCES[$schedule['source']] ?? sprintf('Quellcode 0x%02X', $schedule['source']),
            $days ? implode(', ', $days) : 'keine Tage',
            $schedule['weekly'] ? 'jede Woche' : 'einmalig'
        );
    }

    private function scheduleFormFields(int $slot): array
    {
        $schedule = $this->readScheduleDetails($slot);
        $source = $schedule['source'];
        $fields = [
            'PlanEin' => ['value' => $schedule['start']],
            'PlanAus' => ['value' => $schedule['end']],
            'PlanQuelle' => ['value' => isset(self::SOURCES[$source]) ? $source : 0],
            'PlanStatus' => ['caption' => $this->formatSchedule($slot, $schedule), 'visible' => true]
        ];
        foreach ($schedule['days'] as $field => $selected) {
            $fields[$field] = ['value' => $selected];
        }
        return $fields;
    }

    private function sourceButton(int $code): array
    {
        return [
            'enabled' => $code >= 1 && $code <= 255,
            'onClick' => sprintf('IIY_EingangSetzen($id, %d);', $code)
        ];
    }

    private function fillFormFields(array &$items, array $fields): void
    {
        foreach ($items as &$item) {
            $name = $item['name'] ?? '';
            if (isset($fields[$name])) {
                $item = array_replace($item, $fields[$name]);
            }
            if (isset($item['items'])) {
                $this->fillFormFields($item['items'], $fields);
            }
        }
    }

    private function writeSchedule(int $slot, array $desired): void
    {
        $this->command(0, 0x5A, ($slot << 4) | $desired[0], ...array_slice($desired, 1));
        if ($this->readSchedule($slot) !== $desired) {
            throw new RuntimeException('Der zurückgelesene Zeitplan weicht vom gesendeten Zeitplan ab. Einstellungen am Gerät prüfen.');
        }
    }

    private function validateSlot(int $slot): void
    {
        if ($slot < 1 || $slot > 7) {
            throw new InvalidArgumentException('Zeitplan-Nr. muss zwischen 1 und 7 liegen.');
        }
    }

    private function parseTime(string $time): array
    {
        if (!preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/D', trim($time), $matches)) {
            throw new InvalidArgumentException('Uhrzeit als HH:MM eingeben.');
        }
        return [(int) $matches[1], (int) $matches[2]];
    }

    private function validatePercent(int $percent): void
    {
        if ($percent < 0 || $percent > 100) {
            throw new InvalidArgumentException('Der Wert muss zwischen 0 und 100 liegen.');
        }
    }

    private function query(int $code, int $minimum, int ...$args): array
    {
        $data = substr($this->command($code, $code, ...$args), 7, -1);
        if (strlen($data) < $minimum) {
            throw new RuntimeException(sprintf('Unvollständige Daten für Befehl 0x%02X.', $code));
        }
        // Nur die in dieser Protokollrevision definierten Daten übernehmen.
        return array_values(unpack('C*', substr($data, 0, $minimum)));
    }

    private function queryText(int $code, int $selector): string
    {
        $label = trim(substr($this->command($code, $code, $selector), 7, -1));
        if ($label === '' || preg_match('/^[\x20-\x7E]+$/D', $label) !== 1) {
            throw new RuntimeException(sprintf('Ungültige Textantwort für Befehl 0x%02X.', $code));
        }
        return $label;
    }

    private function command(int $expected, int ...$data): string
    {
        return $this->exchange(IiyamaLanProtocol::frame($this->ReadPropertyInteger('MonitorID'), ...$data), $expected);
    }

    private function exchange(string $frame, int $expected): string
    {
        $protocol = new IiyamaLanProtocol();
        try {
            return $protocol->send(trim($this->ReadPropertyString('Adresse')), $this->ReadPropertyInteger('Port'), $frame, $expected);
        } finally {
            $this->SetValue('Erreichbar', $protocol->connected);
        }
    }

    private function validConfiguration(): bool
    {
        $port = $this->ReadPropertyInteger('Port');
        $id = $this->ReadPropertyInteger('MonitorID');
        return filter_var(trim($this->ReadPropertyString('Adresse')), FILTER_VALIDATE_IP) !== false
            && $port >= 1 && $port <= 65535 && $id >= 1 && $id <= 255;
    }

    private function execute(callable $action, bool $timer = false): mixed
    {
        $lock = 'IiyamaLAN.' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($lock, $timer ? 0 : 5000)) {
            if ($timer) {
                return null;
            }
            throw new RuntimeException('Eine andere iiyama-Aktion läuft bereits.');
        }
        try {
            if (!$this->validConfiguration()) {
                throw new RuntimeException('IP-Adresse, Port oder Monitor-ID ist ungültig.');
            }
            $result = $action();
            $this->SetValue('LetzterFehler', '');
            return $result;
        } catch (Throwable $error) {
            $this->SetValue('LetzterFehler', $error->getMessage());
            if ($timer && $error instanceof RuntimeException) {
                return null;
            }
            throw $error;
        } finally {
            IPS_SemaphoreLeave($lock);
        }
    }
}
