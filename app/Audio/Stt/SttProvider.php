<?php

namespace App\Audio\Stt;

/**
 * A speech-to-text service that can listen to Luganda. Add a new one by
 * implementing this and listing it in SttManager and config/luganda.php ('stt.providers').
 */
interface SttProvider
{
    /** Short id stored with each attempt, e.g. "sunbird". */
    public function name(): string;

    public function label(): string;

    /** Configured and ready to use (e.g. has an API token)? */
    public function available(): bool;

    /**
     * What was said in this recording (Luganda). The file is only read, never kept.
     *
     * @throws SttException
     */
    public function transcribe(string $path, string $mime): string;
}
