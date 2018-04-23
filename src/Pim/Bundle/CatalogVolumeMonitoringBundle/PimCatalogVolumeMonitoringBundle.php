<?php

namespace Pim\Bundle\CatalogVolumeMonitoringBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @author    Alexandre Hocquard <alexandre.hocquard@akeneo.com>
 * @copyright 2018 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class PimCatalogVolumeMonitoringBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        $doctrineMappings = [
            realpath(__DIR__ . '/Resources/config/model/doctrine') => 'Pim\Component\CatalogVolumeMonitoring\Volume\Model',
        ];

        $container->addCompilerPass(
            DoctrineOrmMappingsPass::createYamlMappingDriver(
                $doctrineMappings,
                ['doctrine.orm.entity_manager'],
                false
            )
        );
    }
}
