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

class SSE
{
    /**
     * Constructor
     */
    function __construct()
    {

    }
    
    public function stream(callable $func): void
    {
        date_default_timezone_set("America/New_York");
        header("X-Accel-Buffering: no");
        header("Content-Type: text/event-stream");
        header("Cache-Control: no-cache");

        while (!connection_aborted()) {
            $func();
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