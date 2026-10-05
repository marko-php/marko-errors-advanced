<?php

declare(strict_types=1);

namespace Marko\ErrorsAdvanced\Tests\Fixtures;

use Marko\ErrorsAdvanced\AdvancedErrorHandler;

/**
 * Skips real output-buffer clearing (which would close the test runner's own
 * buffers) and captures the status code and headers instead of sending them.
 */
class OutputSafeAdvancedErrorHandler extends AdvancedErrorHandler
{
    public int $buffersClearedCount = 0;

    public ?int $statusCodeSet = null;

    /** @var array<string, string> */
    public array $headersSent = [];

    protected function clearOutputBuffers(): void
    {
        $this->buffersClearedCount++;
    }

    protected function setHttpStatusCode(
        int $code,
    ): void {
        $this->statusCodeSet = $code;
    }

    protected function sendHeader(
        string $name,
        string $value,
    ): void {
        $this->headersSent[$name] = $value;
    }
}
