<?php
/**
 * Class Htmx
 * 
 * Wrapper class to read request headers from and send response header to htmx.
 * 
 * @author Aaron Bishop (github.com/foozbat) 
 */

namespace Fzb;

use Exception;

class HxtmException extends Exception {};

enum HtmxSwap: string
{
    case innerHTML   = 'innerHTML';
    case outerHTML   = 'outerHTML';
    case beforebegin = 'beforebegin';
    case afterbegin  = 'afterbegin';
    case beforeend   = 'beforeend';
    case afterend    = 'afterend';
    case delete      = 'delete';
    case none        = 'none';
}

class Htmx
{
    /* Request Methods */

    /**
     * 	Indicates that the request is via an element using hx-boost
     *
     * @return boolean
     */
    public static function is_boosted(): bool
    {
        return (isset($_SERVER['HTTP_HX_BOOSTED']));
    }

    /**
     * The current URL of the browser
     *
     * @return string|null
     */
    public static function current_url(): ?string
    {
        return $_SERVER['HTTP_HX_CURRENT_URL'] ?? null;
    }

    /**
     * Returns “true” if the request is for history restoration after a miss in the local history cache
     *
     * @return boolean
     */
    public static function is_history_restore_request(): bool
    {
        return $_SERVER['HTTP_HX_HISTORY_RESTORE_REQUEST'] ?? false;
    }

    /**
     * The user response to an hx-prompt
     *
     * @return string|null
     */
    public static function prompt(): ?string
    {
        return $_SERVER['HTTP_HX_PROMPT'] ?? null;
    }


    /**
     * Returns true if the request was from htmx
     *
     * @return boolean
     */
    public static function is_htmx_request(): bool
    {
        return (isset($_SERVER['HTTP_HX_REQUEST']));
    }

    /**
     * Returns the id of the target element if it exists
     *
     * @return string|null
     */
    public static function target_id(): ?string
    {
        return $_SERVER['HTTP_HX_TARGET'] ?? null;
    }

    /**
     * Returns the name of the triggered element if it exists
     *
     * @return string|null
     */
    public static function triggered_name(): ?string
    {
        return $_SERVER['HTTP_HX_TRIGGER_NAME'] ?? null;
    }

    /**
     * Returns the id of the triggered element if it exists
     *
     * @return string|null
     */
    public static function triggered_id(): ?string
    {
        return $_SERVER['HTTP_HX_TRIGGER'] ?? null;
    }

    /* Response Methods */

    /**
     * Allows you to do a client-side redirect that does not do a full page reload
     *
     * @param string $url URL
     * @return void
     */
    public static function location(string $url): void
    {
        self::check_headers();

        header("HX-Location: $url");
    }

    /**
     * Pushes a new url into the history stack
     *
     * @param string $url URL
     * @return void
     */
    public static function push_url(string $url): void
    {
        self::check_headers();

        header("HX-Push-Url: $url");
    }

    /**
     * Can be used to do a client-side redirect to a new location
     *
     * @param string $url URL
     * @return void
     */
    public static function redirect(string $url): void
    {
        self::check_headers();

        header("HX-Redirect: $url");
    }

    /**
     * Triggers the client-side to do a full refresh of the page
     *
     * @return void
     */
    public static function refresh(): void
    {
        self::check_headers();

        header("HX-Refresh: true");
    }

    /**
     * Replaces the current URL in the location bar
     *
     * @param string $url URL
     * @return void
     */
    public static function replace_url(string $url): void
    {
        self::check_headers();

        header("HX-Replace-Url: $url");
    }

    /**
     * Allows you to specify how the response will be swapped. 
     *
     * @param HtmxSwap $swap New htmx swap type to be used.
     * @return void
     */
    public static function reswap(HtmxSwap $swap): void
    {
        self::check_headers();

        header("HX-Replace-Url: $swap");
    }

    /**
     * Updates the target of the content update to a different element on the page
     *
     * @param string $target CSS selector for new target.
     * @return void
     */
    public function retarget(string $target): void
    {
        self::check_headers();

        header("HX-Retarget: $target");
    }

    /**
     * 	Overrides an existing hx-select on the triggering element.
     *
     * @param string $select CSS selector for the part of the response to be swapped in
     * @return void
     */
    public function reselect(string $select): void
    {
        self::check_headers();

        header("HX-Reselect: $select");
    }

    /**
     * Allows you to trigger client-side events
     *
     * @param array|string $events Events to be sent
     * @return void
     */
    public static function trigger(array|string $events)
    {
        self::check_headers();

        header("HX-Trigger: ".self::json_encode_events($events));
    }

    /**
     * Allows you to trigger client-side events after the settle step
     *
     * @param array|string $events Events to be sent
     * @return void
     */
    public static function trigger_after_settle(array|string $events)
    {
        self::check_headers();

        header("HX-Trigger-After-Settle: ".self::json_encode_events($events));
    }

    /**
     * Allows you to trigger client-side events after the swap step
     *
     * @param array|string $events Events to be sent
     * @return void
     */
    public static function trigger_after_swap(array|string $events)
    {
        self::check_headers();

        header("HX-Trigger-After-Swap: ".self::json_encode_events($events));
    }

    /* Helper Methods */

    /**
     * Checks if HTTP headers have been sent
     *
     * @return void
     */
    private static function check_headers(): void
    {
        if (headers_sent()) {
            throw new HtmxException("HTTP headers already sent.");
        }
    }

    /**
     * Takes a list of events and JSON encodes them
     *
     * @param array|string $events Events to be encoded
     * @return string
     */
    private static function json_encode_events(array|string $events): string
    {
        if (is_string($events)) {
            $events = [$events];
        }

        $events_json = [];

        foreach ($events as $k => $v) {
            if (is_numeric($k)) {
                $k = $v;
                $v = '';
            }
            $events_json[$k] = $v;
        }

        return json_encode($events_json);
    }

}