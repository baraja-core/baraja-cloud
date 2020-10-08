<?php

declare(strict_types=1);

namespace Baraja\BarajaCloud;


use Nette\DI\CompilerExtension;

final class BarajaCloudExtension extends CompilerExtension
{
	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition('barajaCloud.cloudManager')
			->setFactory(CloudManager::class)
			->setAutowired(CloudManager::class);
	}
}
