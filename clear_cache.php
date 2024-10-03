<?php

try {
    // Load Composer's autoloader
    require __DIR__.'/vendor/autoload.php';

    // Load Laravel's application
    $app = require_once __DIR__.'/bootstrap/app.php';

    // Clear the route cache
    $app->make(Illuminate\Contracts\Console\Kernel::class)->call('route:clear');
    echo "Route cache cleared<br>";

    // Clear the application cache
    $app->make(Illuminate\Contracts\Console\Kernel::class)->call('cache:clear');
    echo "Application cache cleared<br>";

    // Clear and cache the configuration
    $app->make(Illuminate\Contracts\Console\Kernel::class)->call('config:cache');
    echo "Configuration cache cleared and rebuilt<br>";

    // Create a symbolic link for storage
    // $app->make(Illuminate\Contracts\Console\Kernel::class)->call('storage:link');
    // echo "Storage link created<br>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}