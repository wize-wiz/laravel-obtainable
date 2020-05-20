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
        $this->subscribers();
    }

    public function boot() {
        $this->publishes([
            __DIR__.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'obtainable.php' => config_path('obtainable.php'),
        ]);
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribers() {
        $subscribers = config('obtainable.subscribers', []);
        foreach($subscribers as $subscriber) {
            Event::subscribe($subscriber);
        }
    }

}