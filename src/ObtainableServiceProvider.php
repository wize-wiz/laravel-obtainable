<?php

namespace WizeWiz\Obtainable;

use Illuminate\Support\ServiceProvider;

class ObtainableServiceProvider extends ServiceProvider {

    public function register() {
        $this->mergeConfigFrom(
            __DIR__.'/config/obtainable.php', 'obtainable'
        );
    }

    public function boot() {
        $this->publishes([
            __DIR__.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'obtainable.php' => config_path('obtainable.php'),
        ]);
    }

}