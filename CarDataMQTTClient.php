<?php

/**
 * Minimal MQTT 3.1.1 client for the BMW CarData Stream: TLS connection, login with username and password,
 * subscriptions with QoS 0 (the only QoS the stream delivers) and keep alive.
 */
class CarDataMQTTClient {

    const KEEP_ALIVE = 30;

    private $socket = null;
    private string $buffer = "";
    private int $lastSent = 0;
    private int $lastReceived = 0;

    /**
     * Open the TLS connection and log in
     *
     * @throws Exception        connection failed or login refused by the broker
     */
    public function connect(string $host, int $port, string $clientId, string $username, string $password): void {
        $this->disconnect();

        $socket = @stream_socket_client("tls://$host:$port", $errorCode, $errorMessage, 10);
        if ($socket === false) {
            throw new Exception("connection to $host:$port failed: " . ($errorMessage ?: error_get_last()["message"] ?? $errorCode));
        }
        $this->socket = $socket;
        stream_set_timeout($this->socket, 1);
        $this->buffer = "";
        $this->lastReceived = time();

        // CONNECT with username, password and clean session
        $this->send(0x10, $this->string("MQTT") . chr(4) . chr(0xC2) . pack("n", self::KEEP_ALIVE)
            . $this->string($clientId) . $this->string($username) . $this->string($password));

        $deadline = time() + 10;
        while (($packet = $this->nextPacket()) === null) {
            if (time() > $deadline) throw new Exception("no answer from broker");
            $this->read();
        }
        $returnCode = ord($packet[2][1] ?? "\xFF");
        if ($packet[0] != 2 || $returnCode != 0) {
            throw new Exception("connection refused by broker, return code $returnCode");
        }
    }

    /**
     * Subscribe to a topic filter with QoS 0
     *
     * @throws Exception        connection lost
     */
    public function subscribe(string $topic): void {
        $this->send(0x82, pack("n", 1) . $this->string($topic) . chr(0));
    }

    /**
     * Wait up to one second for messages and keep the connection alive
     *
     * @return string[]         payloads of the received messages
     * @throws Exception        connection lost or subscription refused
     */
    public function loop(): array {
        if (time() - $this->lastSent >= self::KEEP_ALIVE - 5) $this->send(0xC0, "");      // PINGREQ
        if (time() - $this->lastReceived > self::KEEP_ALIVE * 2) throw new Exception("connection timed out");
        $this->read();

        $messages = [];
        while (($packet = $this->nextPacket()) !== null) {
            [$type, $flags, $body] = $packet;
            // PUBLISH: topic, packet id only with QoS > 0, payload
            if ($type == 3) {
                $messages[] = substr($body, 2 + unpack("n", $body)[1] + ($flags & 0x06 ? 2 : 0));
            }
            // SUBACK with failure return code
            if ($type == 9 && ord($body[2] ?? "\x00") == 0x80) throw new Exception("subscription refused by broker");
        }
        return $messages;
    }

    public function disconnect(): void {
        if ($this->socket === null) return;
        @fwrite($this->socket, "\xE0\x00");     // DISCONNECT, the connection may already be gone
        fclose($this->socket);
        $this->socket = null;
    }

    /**
     * Read the available data into the buffer, waits up to one second
     *
     * @throws Exception        connection closed
     */
    private function read(): void {
        $data = fread($this->socket, 65536);
        if (($data === false || $data === "") && feof($this->socket)) throw new Exception("connection closed by broker");
        if ($data) {
            $this->buffer .= $data;
            $this->lastReceived = time();
        }
    }

    /**
     * Take the next complete packet out of the buffer
     *
     * @return array|null       [type, flags, body] or null when no complete packet is buffered
     */
    private function nextPacket(): ?array {
        // remaining length: 7 bits per byte, the highest bit marks a following byte
        $length = 0;
        $position = 1;
        do {
            if (!isset($this->buffer[$position])) return null;
            $byte = ord($this->buffer[$position]);
            $length += ($byte & 0x7F) << (7 * ($position - 1));
            $position++;
        } while ($byte & 0x80);
        if (strlen($this->buffer) < $position + $length) return null;

        $header = ord($this->buffer[0]);
        $body = substr($this->buffer, $position, $length);
        $this->buffer = substr($this->buffer, $position + $length);
        return [$header >> 4, $header & 0x0F, $body];
    }

    /**
     * @throws Exception        connection lost
     */
    private function send(int $header, string $body): void {
        $length = strlen($body);
        $packet = chr($header);
        do {
            $packet .= chr(($length & 0x7F) | ($length > 0x7F ? 0x80 : 0));
            $length >>= 7;
        } while ($length > 0);
        $packet .= $body;

        for ($written = 0; $written < strlen($packet); $written += $bytes) {
            $bytes = @fwrite($this->socket, substr($packet, $written));
            if (!$bytes) throw new Exception("connection lost while sending");
        }
        $this->lastSent = time();
    }

    private function string(string $value): string {
        return pack("n", strlen($value)) . $value;
    }
}
