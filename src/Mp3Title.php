<?php

namespace Hschottm\AurigaBundle;

/** Read only bounded ID3 metadata, never the full audio recording. */
final class Mp3Title
{
    public static function read(string $path): string
    {
        $stream = @fopen($path, 'rb');
        if ($stream === false) return '';
        try {
            $header = fread($stream, 10);
            if (strlen($header) === 10 && substr($header, 0, 3) === 'ID3') {
                $version = ord($header[3]);
                $size = self::sync(substr($header, 6, 4));
                if (in_array($version, [2, 3, 4], true) && $size > 0 && $size <= 1048576) {
                    $body = fread($stream, $size);
                    if (strlen($body) === $size) {
                        $title = self::frames($body, $version, ord($header[5]));
                        if ($title !== '') return $title;
                    }
                }
            }
            // ID3v1 title is a 30-byte Latin-1 field at the end of the file.
            if (fseek($stream, -128, SEEK_END) === 0) {
                $tag = fread($stream, 128);
                if (strlen($tag) === 128 && substr($tag, 0, 3) === 'TAG') {
                    return self::text("\0".substr($tag, 3, 30));
                }
            }
            return '';
        } finally {
            fclose($stream);
        }
    }

    private static function sync(string $bytes): int
    {
        if (strlen($bytes) !== 4) return -1;
        $value = 0;
        foreach (str_split($bytes) as $byte) {
            if (ord($byte) & 128) return -1;
            $value = ($value << 7) | ord($byte);
        }
        return $value;
    }

    private static function frames(string $body, int $version, int $flags): string
    {
        if ($version === 2 && ($flags & 64)) return ''; // Compressed tag.
        if ($version < 4 && ($flags & 128)) $body = str_replace("\xff\0", "\xff", $body);
        $offset = 0;
        if ($version >= 3 && ($flags & 64)) {
            if (strlen($body) < 4) return '';
            $offset = $version === 4 ? self::sync(substr($body, 0, 4)) : unpack('N', substr($body, 0, 4))[1] + 4;
            if ($offset < 4 || $offset > strlen($body)) return '';
        }
        $headerSize = $version === 2 ? 6 : 10;
        while ($offset + $headerSize <= strlen($body)) {
            $id = substr($body, $offset, $version === 2 ? 3 : 4);
            if (!preg_match('/^[A-Z0-9]+$/D', $id)) break;
            $size = $version === 2 ? unpack('N', "\0".substr($body, $offset + 3, 3))[1]
                : ($version === 4 ? self::sync(substr($body, $offset + 4, 4)) : unpack('N', substr($body, $offset + 4, 4))[1]);
            $frameFlags = $version === 2 ? 0 : ord($body[$offset + 9]);
            $offset += $headerSize;
            if ($size <= 0 || $size > strlen($body) - $offset) break;
            if ($id === 'TIT2' || $id === 'TT2') {
                $value = substr($body, $offset, $size);
                // Encrypted/compressed frames require decoding outside this reader's scope.
                if (($version === 3 && ($frameFlags & 192)) || ($version === 4 && ($frameFlags & 12))) return '';
                if ($version === 4 && (($flags & 128) || ($frameFlags & 2))) $value = str_replace("\xff\0", "\xff", $value);
                if (($version === 3 && ($frameFlags & 32)) || ($version === 4 && ($frameFlags & 64))) $value = substr($value, 1);
                if ($version === 4 && ($frameFlags & 1)) $value = substr($value, 4);
                return self::text($value);
            }
            $offset += $size;
        }
        return '';
    }

    private static function text(string $value): string
    {
        if ($value === '') return '';
        $encoding = [0 => 'ISO-8859-1', 1 => 'UTF-16', 2 => 'UTF-16BE', 3 => 'UTF-8'][ord($value[0])] ?? null;
        if ($encoding === null) return '';
        $text = mb_convert_encoding(substr($value, 1), 'UTF-8', $encoding);
        $text = explode("\0", $text)[0];
        return trim(preg_replace('/[\x00-\x1f\x7f]/u', '', $text));
    }
}
