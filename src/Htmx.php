<?php

namespace Fzb;

class Htmx
{
    static function trigger(mixed $events)
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

        $events_json = json_encode($events_json);
        
        header("HX-Trigger: $events_json");

        //echo "HX-Trigger: $events_json";
    }
}