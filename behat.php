<?php

declare(strict_types=1);

use App\Tests\UseCase\UserContext;
use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Filter\TagFilter;
use Behat\Config\Profile;
use Behat\Config\Suite;
use FriendsOfBehat\SymfonyExtension\ServiceContainer\SymfonyExtension;

return new Config()
    // Default profile: Use case testing (NO Symfony, pure application testing)
    ->withProfile(new Profile('default')
        ->withSuite(new Suite('usecase')
            ->withContexts(UserContext::class)
            ->withPaths('%paths.base%/features')
            ->withFilter(new TagFilter('~@e2e'))))
    // E2E profile: Full integration testing (WITH Symfony)
    // Does NOT inherit default's suites - only runs e2e suite
    ->withProfile(new Profile('e2e')
        ->withExtension(new Extension(SymfonyExtension::class, [
            'bootstrap' => 'tests/bootstrap.php',
            'kernel' => [
                'class' => 'App\Kernel',
                'environment' => 'test',
                'debug' => true,
            ],
        ]))
        ->withSuite(new Suite('e2e')
            ->withContexts(App\Tests\E2E\UserContext::class)
            ->withPaths('%paths.base%/features')))
;
