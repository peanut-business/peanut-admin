<?php
declare(strict_types=1);

namespace app\common\service\notice\driver\mail;

/**
 * SMTP 邮件驱动（原生 socket 实现，无外部依赖）
 *
 * 配置 key（pa_config type=notice，name=mail_smtp，value=JSON）：
 *   host, port, username, password, from_name, encryption(ssl|tls|none)
 */
class SmtpMail
{
    private string $host;
    private int    $port;
    private string $username;
    private string $password;
    private string $fromName;
    private string $encryption;
    private string $error = '';

    public function __construct(array $config)
    {
        $this->host       = (string) ($config['host']       ?? '');
        $this->port       = (int)    ($config['port']       ?? 465);
        $this->username   = (string) ($config['username']   ?? '');
        $this->password   = (string) ($config['password']   ?? '');
        $this->fromName   = (string) ($config['from_name']  ?? '');
        $this->encryption = strtolower((string) ($config['encryption'] ?? 'ssl'));
    }

    /**
     * 发送邮件
     * @param string $to      收件人地址
     * @param string $subject 主题
     * @param string $body    HTML 正文
     */
    public function send(string $to, string $subject, string $body): bool
    {
        $scheme = match ($this->encryption) {
            'ssl'  => 'ssl',
            'tls'  => '',   // 先明文握手，后 STARTTLS
            default => '',
        };

        $host = $scheme ? "{$scheme}://{$this->host}" : $this->host;

        $sock = @fsockopen($host, $this->port, $errno, $errstr, 10);
        if (!$sock) {
            $this->error = "连接失败: {$errstr} ({$errno})";
            return false;
        }

        try {
            $this->expect($sock, '220');

            $this->send_cmd($sock, "EHLO {$this->host}");
            $resp = $this->read($sock);

            if ($this->encryption === 'tls') {
                $this->send_cmd($sock, 'STARTTLS');
                $this->expect($sock, '220');
                stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->send_cmd($sock, "EHLO {$this->host}");
                $this->read($sock);
            }

            $this->send_cmd($sock, 'AUTH LOGIN');
            $this->expect($sock, '334');
            $this->send_cmd($sock, base64_encode($this->username));
            $this->expect($sock, '334');
            $this->send_cmd($sock, base64_encode($this->password));
            $this->expect($sock, '235');

            $fromAddr = $this->username;
            $this->send_cmd($sock, "MAIL FROM:<{$fromAddr}>");
            $this->expect($sock, '250');
            $this->send_cmd($sock, "RCPT TO:<{$to}>");
            $this->expect($sock, '250');
            $this->send_cmd($sock, 'DATA');
            $this->expect($sock, '354');

            $fromDisplay = $this->fromName
                ? '=?UTF-8?B?' . base64_encode($this->fromName) . "?= <{$fromAddr}>"
                : $fromAddr;
            $subjectEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $boundary = 'boundary_' . md5(uniqid('', true));

            $message = implode("\r\n", [
                "From: {$fromDisplay}",
                "To: {$to}",
                "Subject: {$subjectEncoded}",
                'MIME-Version: 1.0',
                "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
                "Date: " . date('r'),
                '',
                "--{$boundary}",
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                '',
                chunk_split(base64_encode($body)),
                "--{$boundary}--",
                '.',
            ]);

            fwrite($sock, $message . "\r\n");
            $this->expect($sock, '250');

            $this->send_cmd($sock, 'QUIT');
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();
            fclose($sock);
            return false;
        }

        fclose($sock);
        return true;
    }

    public function getError(): string
    {
        return $this->error;
    }

    /** @param resource $sock */
    private function send_cmd($sock, string $cmd): void
    {
        fwrite($sock, $cmd . "\r\n");
    }

    /** @param resource $sock */
    private function read($sock): string
    {
        $resp = '';
        while ($line = fgets($sock, 512)) {
            $resp .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        return $resp;
    }

    /**
     * 读取响应并断言状态码
     * @param resource $sock
     * @throws \RuntimeException
     */
    private function expect($sock, string $code): string
    {
        $resp = $this->read($sock);
        if (!str_starts_with(trim($resp), $code)) {
            throw new \RuntimeException("SMTP expect {$code}, got: " . trim($resp));
        }
        return $resp;
    }
}
