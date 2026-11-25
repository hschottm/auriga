<?php

declare(strict_types=1);
namespace Hschottm\AurigaBundle;

interface RecordingRegistrar
{
    /** Return the binary UUID of the registered file. */
    public function register(string $path): string;
}
