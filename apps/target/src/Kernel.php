<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait {
        MicroKernelTrait::configureContainer as private doConfigureContainer;
    }

    private function configureContainer(
        ContainerConfigurator $container,
        LoaderInterface $loader,
        ContainerBuilder $builder,
    ): void {
        $configDir = preg_replace('{/config$}', '/{config}', $this->getConfigDir());

        $this->doConfigureContainer($container, $loader, $builder);

        if (is_dir($this->getConfigDir() . '/services')) {
            $container->import($configDir . '/services/*.yaml');
            $container->import($configDir . '/services/' . $this->environment . '/*.yaml');
        }
    }
}
