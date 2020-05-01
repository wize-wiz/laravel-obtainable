<?php

namespace WizeWiz\Obtainable;

use App\Events\TestEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class ObtainableServiceProvider extends ServiceProvider {

    public function register() {
        $this->mergeConfigFrom(
            __DIR__.'/config/obtainable.php', 'obtainable'
        );
    }

    public function boot() {
        $this->events();

        $this->publishes([
            __DIR__.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'obtainable.php' => config_path('obtainable.php'),
        ]);
    }

    /**
     * @todo: cache this procedure to speed up things.
     */
    public function events() {
        $events = config('obtainable.events', []);
        foreach($events as $event_name => $listener) {
            $method = 'on'.last(explode('\\', $event_name));
            Event::listen($event_name, function($event) use($listener, $method, $event_name) {
                $listener::$method($event_name, $event);
            });
        }

        foreach(config('obtainable.wildcards', []) as $event_name => $listeners) {
            Event::listen($event_name, function($event, $data) use($listeners) {
                foreach($listeners as $listener) {
                    if(isset($listener::$events[$event])) {
                        // @note: wildcard does not have the event object.
                        $listener::handleEvent($event, isset($data[0]) ? $data[0] : $data);
                    }
                }
            });
        }
    }
}