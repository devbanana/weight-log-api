<?php

declare(strict_types=1);

namespace App;

use App\Infrastructure\DependencyInjection\ClockTimezoneValidatorPass;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    #[\Override]
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ClockTimezoneValidatorPass());
    }
}
