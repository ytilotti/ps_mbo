<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */
declare(strict_types=1);

namespace PrestaShop\Module\Mbo\Distribution\Config;

use Db;
use Doctrine\DBAL\Query\QueryException;
use PrestaShop\Module\Mbo\Distribution\Config\Appliers\Factory as AppliersFactory;
use PrestaShop\Module\Mbo\Distribution\Config\Exception\CannotSaveConfigException;
use PrestaShop\Module\Mbo\Distribution\Config\Exception\InvalidConfigException;
use PrestaShopDatabaseException;

final class Applier
{
    /**
     * @var AppliersFactory
     */
    private $appliersFactory;

    public function __construct(AppliersFactory $appliersFactory)
    {
        $this->appliersFactory = $appliersFactory;
    }

    /**
     * This method will receive an array of config objects and apply them.
     *
     * @param Config[] $config
     *
     * @throws QueryException
     * @throws InvalidConfigException
     * @throws CannotSaveConfigException
     * @throws PrestaShopDatabaseException
     */
    public function apply(array $configCollection, string $psVersion, string $mboVersion)
    {
        foreach ($configCollection as $config) {
            if ($this->canBeApplied($config, $psVersion, $mboVersion)) {
                $this->applyConfig($config);
            }
        }
    }

    /**
     * This method will determinate if the config given can be applied depending on the psVersion and mboVersion.
     *
     * @param Config $config
     */
    private function canBeApplied(Config $config, string $psVersion, string $mboVersion): bool
    {
        return $psVersion === $config->getPsVersion() &&
            $mboVersion === $config->getMboVersion() &&
            true !== $config->isApplied();
    }

    /**
     * @param Config $config
     *
     * @return bool|void
     *
     * @throws InvalidConfigException
     */
    private function applyConfig(Config $config)
    {
        $applier = $this->appliersFactory->get($config->getConfigKey());

        if (null === $applier) {
            return true;
        }

        if ($applier->apply($config) && null !== $config->getConfigId()) {
            $sql = [];
            $sql[] = 'UPDATE `' . _DB_PREFIX_ . 'mbo_api_config` SET `applied` = 1 WHERE `id_mbo_api_config`=' . $config->getConfigId();

            foreach ($sql as $query) {
                if (Db::getInstance()->execute($query) == false) {
                    throw new QueryException($this->db->getMsgError());
                }
            }
        }
    }
}
