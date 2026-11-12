<?php
require __DIR__.'/../src/Mp3Title.php';
use Hschottm\AurigaBundle\Mp3Title;
function syncSize(int $n): string { return chr(($n >> 21) & 127).chr(($n >> 14) & 127).chr(($n >> 7) & 127).chr($n & 127); }
$file = tempnam(sys_get_temp_dir(), 'auriga-id3-');
function titleCheck(string $bytes, string $expected): void {
    global $file;
    file_put_contents($file, $bytes);
    if (Mp3Title::read($file) !== $expected) throw new RuntimeException('ID3 title mismatch');
}
try {
    $v1 = 'TAG'.str_pad("Gr\xfc\xdfe",30,"\0").str_repeat("\0",95);
    titleCheck($v1, 'Grüße');
    foreach ([2,3,4] as $version) {
        foreach ([0=>'ISO-8859-1',1=>'UTF-16LE',2=>'UTF-16BE',3=>'UTF-8'] as $code=>$encoding) {
            $text = chr($code).($code === 1 ? "\xff\xfe" : '').mb_convert_encoding('Grüße <Titel>', $encoding, 'UTF-8');
            $frame = $version === 2 ? 'TT2'.substr(pack('N',strlen($text)),1) : 'TIT2'.($version === 4 ? syncSize(strlen($text)) : pack('N',strlen($text)))."\0\0";
            $body = $frame.$text;
            titleCheck('ID3'.chr($version)."\0\0".syncSize(strlen($body)).$body.$v1, 'Grüße <Titel>');
        }
    }
    $longTitle = '1. Im frostigen Griff des Winters';
    foreach ([3,4] as $version) {
        $sizeBytes = $version === 4 ? static fn($n) => syncSize($n) : static fn($n) => pack('N', $n);
        $value = "\0".$longTitle;
        $titleFrame = 'TIT2'.$sizeBytes(strlen($value))."\0\0".$value;
        $picture = 'APIC'.$sizeBytes(1400000)."\0\0".str_repeat('x',1400000);
        foreach ([$titleFrame.$picture, $picture.$titleFrame] as $body) {
            titleCheck('ID3'.chr($version)."\0\0".syncSize(strlen($body)).$body.$v1, $longTitle);
        }
    }
    titleCheck('no tags', '');
    titleCheck('ID3'."\x03\0\0".syncSize(100).'short', '');
    titleCheck('ID3'."\x03\0\0".syncSize(2000000).$v1, 'Grüße');
    titleCheck('ID3'."\x04\0\0".syncSize(10).'TIT2'."\xff\xff\xff\xff\0\0", '');
    echo "OK: ID3v1/v2.2/v2.3/v2.4, text encodings, v2 priority, missing/truncated/oversized tags\n";
} finally { unlink($file); }
