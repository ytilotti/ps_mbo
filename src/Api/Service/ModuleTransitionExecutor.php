<?php

namespace PrestaShop\Module\Mbo\Api\Service;

use PrestaShop\Module\Mbo\Api\Exception\QueryParamsException;
use PrestaShop\Module\Mbo\Module\Command\ModuleStatusTransitionCommand;
use PrestaShop\Module\Mbo\Module\CommandHandler\ModuleStatusTransitionCommandHandler;
use Tools;

class ModuleTransitionExecutor implements ServiceExecutorInterface
{
    const SERVICE = 'module';

    /**
     * @var ModuleStatusTransitionCommandHandler
     */
    private $moduleStatusTransitionCommandHandler;

    public function __construct(ModuleStatusTransitionCommandHandler $moduleStatusTransitionCommandHandler)
    {
        $this->moduleStatusTransitionCommandHandler = $moduleStatusTransitionCommandHandler;
    }

    /**
     * @inheritDoc
     */
    public function canExecute(string $service): bool
    {
        return self::SERVICE === $service;
    }

    /**
     * @inheritDoc
     */
    public function execute(...$parameters): array
    {
        $transition = Tools::getValue('action');
        $moduleName = Tools::getValue('module');
        $source = Tools::getValue('source', null);

        if (empty($transition) || empty($moduleName)) {
            throw new QueryParamsException('You need transition and module parameters');
        }
        $command = new ModuleStatusTransitionCommand($transition, $moduleName, $source);

        /** @var \PrestaShop\Module\Mbo\Module\Module $module */
        $module = $this->moduleStatusTransitionCommandHandler->handle($command);

        $moduleUrls = $module->get('urls');
        $configUrl = (bool) $module->get('is_configurable') && isset($moduleUrls['configure']) ? $this->generateTokenizedModuleActionUrl($moduleUrls['configure']) : null;

        return [
            'message' => 'Transition successfully executed',
            'module_status' => $module->getStatus(),
            'version' => $module->get('version'),
            'config_url' => $configUrl,
        ];
    }

    private function generateTokenizedModuleActionUrl($url): string
    {
        $components = parse_url($url);
        $baseUrl = ($components['path'] ?? '');
        $queryParams = [];
        if (isset($components['query'])) {
            $query = $components['query'];

            parse_str($query, $queryParams);
        }

        if (!isset($queryParams['_token'])) {
            return $url;
        }

        $adminToken = Tools::getValue('admin_token');
        $queryParams['_token'] = $adminToken;

        $url = $baseUrl . '?' . http_build_query($queryParams, '', '&');
        if (isset($components['fragment']) && $components['fragment'] !== '') {
            /* This copy-paste from Symfony's UrlGenerator */
            $url .= '#' . strtr(rawurlencode($components['fragment']), ['%2F' => '/', '%3F' => '?']);
        }

        return $url;
    }
}
