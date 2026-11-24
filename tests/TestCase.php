<?php

namespace Tests;
abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase {
    public function createApplication() {
        $app = parent::createApplication();
        if (!$app->environment('testing') || $app['config']->get('database.connections.mysql.database') !== 'cheflagbe_test') throw new \RuntimeException('Tests require a separate cheflagbe_test database.');
        return $app;
    }
}
