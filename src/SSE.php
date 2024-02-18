<?php
/**
 * Class Fzb\Benchmark
 * 
 * Helper class to simplify SSE messaging
 * 
 * usage: Instantiate with $bm = new Fzb\Benchmark();
 * 
 * @author  Aaron Bishop (github.com/foozbat)
 */

declare(strict_types=1);

namespace Fzb;

use Exception;

class SSEException extends Exception { };

class SSE
{
    /**
     * Constructor
     */
    function __construct(callable $event_stream, callable $shutdown = null)
    {
        if (!is_callable($event_stream)) {
            throw new SSEException("SSE must be provided with a valid callback for the event stream.");
        }
        if (!is_null($shutdown)) {
            if (!is_callable($shutdown)) {
                throw new SSEException("Provided shutdown function is not a valid callback.");
            }

            register_shutdown_function($shutdown);
        }
        
       if (ob_get_level()) {
            ob_end_clean();
        }

        date_default_timezone_set("America/New_York");
        header("X-Accel-Buffering: no");
        header("Content-Type: text/event-stream");
        header("Cache-Control: no-cache");

        while (!connection_aborted()) {
            $event_stream($this);
        }
    }

    public function message(string $message, string $data, ?string $id = null): void
    {
        echo "event: $message" . PHP_EOL;
        if ($id) {
            echo "id: $id" . PHP_EOL;
        }
        echo "data: $data" . PHP_EOL;
        echo PHP_EOL;
    
        ob_end_flush();
        flush();
    }
}