<?php

declare(strict_types=1);

final class IiyamaLanProtocol
{
    public bool $connected = false;

    public static function frame(int $id, int ...$data): string
    {
        $frame = pack('C*', 0xA6, $id, 0, 0, 0, count($data) + 2, 1, ...$data);
        return $frame . chr(self::checksum($frame));
    }

    public static function fromHex(string $hex): string
    {
        $hex = preg_replace('/[\s-]/', '', $hex);
        if (preg_match('/^(?:[a-f0-9]{2}){8,261}$/iD', $hex) !== 1) {
            throw new InvalidArgumentException('Vollständige Hex-Bytes inklusive Prüfsumme erwartet.');
        }
        $frame = hex2bin($hex);
        if (ord($frame[0]) !== 0xA6 || ord($frame[5]) + 6 !== strlen($frame) || self::checksum($frame) !== 0) {
            throw new InvalidArgumentException('Header, Länge oder Prüfsumme ist ungültig.');
        }
        return $frame;
    }

    public function send(string $address, int $port, string $frame, int $expected): string
    {
        $host = str_contains($address, ':') ? '[' . $address . ']' : $address;
        $endpoint = $host . ':' . $port;
        $this->connected = false;
        // Erst nach erfolgreichem TCP-Verbindungsaufbau werden Befehlsbytes geschrieben.
        $socket = @stream_socket_client('tcp://' . $endpoint, $errorCode, $error, 3);
        if ($socket === false) {
            throw new RuntimeException('TCP ' . $endpoint . ' ist nicht erreichbar. Befehl wurde nicht gesendet. ' . $error);
        }
        $this->connected = true;

        try {
            stream_set_timeout($socket, 3);
            for ($offset = 0; $offset < strlen($frame); $offset += $written) {
                $written = @fwrite($socket, substr($frame, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('TCP-Verbindung beim Senden unterbrochen.');
                }
            }
            $id = ord($frame[1]);
            if ($id === 0) {
                return '';
            }

            $deadline = microtime(true) + 3;
            for ($i = 0; $i < 4; $i++) {
                $header = $this->read($socket, 5, $deadline);
                $length = ord($header[4]);
                if (ord($header[0]) !== 0x21 || ord($header[1]) !== $id || $length < 3) {
                    throw new RuntimeException('Ungültiger Antwort-Header vom iiyama.');
                }
                $body = $this->read($socket, $length, $deadline);
                $response = $header . $body;
                if (ord($body[0]) !== 1 || self::checksum($response) !== 0) {
                    throw new RuntimeException('Ungültige Prüfsumme oder Antwort vom iiyama.');
                }
                $code = ord($body[1]);
                if ($code === 0 && ($length !== 4 || ord($body[2]) !== 0)) {
                    throw new RuntimeException('Der iiyama hat den Befehl abgelehnt (Antwort ' . bin2hex($body) . ').');
                }
                if ($expected === -1 || $expected === $code) {
                    return $response;
                }
            }
            throw new RuntimeException('Die erwartete Antwort fehlt.');
        } finally {
            fclose($socket);
        }
    }

    private function read($socket, int $length, float $deadline): string
    {
        $result = '';
        while (strlen($result) < $length) {
            $remaining = (int) ceil(($deadline - microtime(true)) * 1_000_000);
            if ($remaining <= 0) {
                throw new RuntimeException('Zeitüberschreitung beim Lesen der Antwort.');
            }
            stream_set_timeout($socket, intdiv($remaining, 1_000_000), $remaining % 1_000_000);
            $chunk = @fread($socket, $length - strlen($result));
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException(stream_get_meta_data($socket)['timed_out']
                    ? 'Zeitüberschreitung beim Lesen der Antwort.'
                    : 'TCP-Verbindung vor Ende der Antwort geschlossen.');
            }
            $result .= $chunk;
        }
        return $result;
    }

    private static function checksum(string $value): int
    {
        $checksum = 0;
        for ($i = 0; $i < strlen($value); $i++) {
            $checksum ^= ord($value[$i]);
        }
        return $checksum;
    }
}
