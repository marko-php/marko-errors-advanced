<?php

declare(strict_types=1);

namespace Marko\ErrorsAdvanced;

use ErrorException;
use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\Errors\Contracts\FormatterInterface;
use Marko\Errors\ErrorReport;
use Marko\Errors\Severity;
use Marko\ErrorsSimple\CodeSnippetExtractor;
use Marko\ErrorsSimple\Environment;
use Marko\ErrorsSimple\Formatters\BasicHtmlFormatter;
use Marko\ErrorsSimple\Formatters\JsonFormatter;
use Marko\ErrorsSimple\Formatters\TextFormatter;
use Marko\ErrorsSimple\HttpErrorStatus;
use Throwable;

class AdvancedErrorHandler implements ErrorHandlerInterface
{
    private Environment $environment;

    private FormatterInterface $prettyHtmlFormatter;

    private TextFormatter $textFormatter;

    private BasicHtmlFormatter $fallbackFormatter;

    private JsonFormatter $jsonFormatter;

    protected bool $registered = false;

    protected mixed $previousExceptionHandler = null;

    protected mixed $previousErrorHandler = null;

    protected bool $handledFatalError = false;

    /**
     * The pretty formatter defaults to one built for the real environment, so
     * production gets the safe generic page. The container cannot autowire
     * the nullable FormatterInterface; module.php binds this class with a
     * closure that passes the Environment.
     */
    public function __construct(
        ?Environment $environment = null,
        ?FormatterInterface $prettyHtmlFormatter = null,
    ) {
        $this->environment = $environment ?? new Environment();
        $extractor = new CodeSnippetExtractor();
        $this->prettyHtmlFormatter = $prettyHtmlFormatter ?? new PrettyHtmlFormatter(
            environment: $this->environment->isProduction() ? 'production' : 'development',
        );
        $this->jsonFormatter = new JsonFormatter($this->environment);
        $this->textFormatter = new TextFormatter(
            $this->environment,
            $extractor,
        );
        $this->fallbackFormatter = new BasicHtmlFormatter(
            $this->environment,
            $extractor,
        );
    }

    public function handle(
        ErrorReport $report,
    ): void {
        $this->clearOutputBuffers();

        if ($this->environment->isCli()) {
            echo $this->textFormatter->format($report);

            return;
        }

        $this->setHttpStatusCode(HttpErrorStatus::statusCode($report->throwable));

        foreach (HttpErrorStatus::headers($report->throwable) as $name => $value) {
            $this->sendHeader($name, $value);
        }

        if ($this->environment->acceptsJson()) {
            $this->sendHeader('Content-Type', JsonFormatter::CONTENT_TYPE);

            try {
                echo $this->jsonFormatter->format($report);
            } catch (Throwable) {
                echo '{"message":"Server Error"}';
            }

            return;
        }

        $this->sendHeader('Content-Type', BasicHtmlFormatter::CONTENT_TYPE);

        try {
            echo $this->prettyHtmlFormatter->format($report);
        } catch (Throwable) {
            echo $this->fallbackFormatter->format($report);
        }
    }

    protected function clearOutputBuffers(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    protected function setHttpStatusCode(
        int $code,
    ): void {
        if (!headers_sent()) {
            http_response_code($code);
        }
    }

    protected function sendHeader(
        string $name,
        string $value,
    ): void {
        if (!headers_sent()) {
            header("$name: $value");
        }
    }

    public function handleException(
        Throwable $exception,
    ): void {
        $report = ErrorReport::fromThrowable($exception, Severity::Error);
        $this->handle($report);
    }

    protected function handleNonFatal(
        ErrorReport $report,
    ): void {
        if (!$this->environment->isCli()) {
            return;
        }

        $color = $report->severity->color();
        $reset = "\033[0m";
        $label = $report->severity->label();

        fwrite(STDERR, "$color[$label]$reset $report->message in $report->file:$report->line\n");
    }

    public function handleError(
        int $level,
        string $message,
        string $file,
        int $line,
    ): bool {
        if (!(error_reporting() & $level)) {
            return false;
        }

        $exception = new ErrorException($message, 0, $level, $file, $line);
        $severity = Severity::fromErrorLevel($level);

        if ($severity === Severity::Deprecated || $severity === Severity::Notice) {
            $report = ErrorReport::fromThrowable($exception, $severity);
            $this->handleNonFatal($report);

            return true;
        }

        $this->handleException($exception);

        return true;
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error === null) {
            return;
        }

        $fatalTypes = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;

        if (!($error['type'] & $fatalTypes)) {
            return;
        }

        if ($this->handledFatalError) {
            return;
        }

        $this->handledFatalError = true;
        $this->handleError($error['type'], $error['message'], $error['file'], $error['line']);
    }

    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        $this->previousExceptionHandler = set_exception_handler([$this, 'handleException']);
        $this->previousErrorHandler = set_error_handler([$this, 'handleError']);
        register_shutdown_function([$this, 'handleShutdown']);
        $this->registered = true;
    }

    public function unregister(): void
    {
        if (!$this->registered) {
            return;
        }

        restore_exception_handler();
        if ($this->previousExceptionHandler !== null) {
            set_exception_handler($this->previousExceptionHandler);
        }

        restore_error_handler();
        if ($this->previousErrorHandler !== null) {
            set_error_handler($this->previousErrorHandler);
        }

        $this->registered = false;
    }
}
