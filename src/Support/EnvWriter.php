<?php

namespace AndreaLagaccia\MailerTransport\Support;

use RuntimeException;

/**
 * Sets keys in a dotenv file, replacing existing lines and appending the
 * missing ones, so the install command can be run again safely.
 */
class EnvWriter
{
    public function __construct(protected string $path) {}

    /**
     * @param  array<string, string|null>  $values
     */
    public function set(array $values): void
    {
        $content = file_exists($this->path) ? (string) file_get_contents($this->path) : '';

        foreach ($values as $key => $value) {
            $content = $this->setKey($content, $key, $this->quote($value));
        }

        if (file_put_contents($this->path, $content) === false) {
            throw new RuntimeException("Impossibile scrivere {$this->path}");
        }
    }

    protected function setKey(string $content, string $key, string $value): string
    {
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
        $line = "{$key}={$value}";

        if (preg_match($pattern, $content)) {
            return (string) preg_replace($pattern, $line, $content, 1);
        }

        if ($content !== '' && ! str_ends_with($content, "\n")) {
            $content .= "\n";
        }

        return $content.$line."\n";
    }

    protected function quote(?string $value): string
    {
        $value ??= '';

        if ($value === '' || preg_match('/^[A-Za-z0-9_.\/:@+-]+$/', $value)) {
            return $value;
        }

        return '"'.str_replace('"', '\\"', $value).'"';
    }
}
