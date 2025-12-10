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
        ->withExtension(new Extension(SymfonyExtension::class, [
            'bootstrap' => 'tests/bootstrap.php',
            'kernel' => [
                'class' => 'App\Infrastructure\Kernel',
                'environment' => 'test',
                'debug' => true,
            ],
        ]))
        ->withSuite(new Suite('usecase')
            ->withContexts(UserContext::class)
            ->withPaths('%paths.base%/features')
            ->withFilter(new TagFilter('~@e2e')))
        ->withSuite(new Suite('e2e')
            ->withContexts(App\Tests\E2E\UserContext::class)
            ->withPaths('%paths.base%/features')))
;
