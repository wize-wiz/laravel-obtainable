<?php

namespace WizeWiz\Obtainable\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;

trait TestEnvironment {

    static $CHANNEL = 'obtainable-test';

    protected function getEnvironmentSetUp($app) {
        $this->loadLogging($app);
        $this->loadCache($app);
        $this->log('Environment setup complete.');
    }

    protected function ignoreTest() {
        return false;
    }

    protected function setUp() : void {
        parent::setUp();
        if($this->ignoreTest()) {
            $this->markTestIncomplete('@todo');
            return;
        }
    }

    protected function getPackageProviders($app) {
        return ['WizeWiz\Obtainable\ObtainableServiceProvider'];
    }
    
    protected function loadCache($app) {
        $app['config']->set('cache.default', 'array');
    }
    
    protected function loadLogging(Application $app) {
        $app['config']->set('logging.channels.' . static::$CHANNEL, [
            'driver' => 'single',
            'path' => storage_path('logs/'.static::$CHANNEL.'.log'),
            'level' => 'info',
        ]);
    }

    /**
     * Log file.
     */
    protected function Log($msg) {
        Log::channel(static::$CHANNEL)->info($msg);
    }

}