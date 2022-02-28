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

namespace PrestaShop\Module\Mbo\Modules;

use Exception;
use Module as LegacyModule;
use PrestaShop\PrestaShop\Core\Addon\Module\ModuleInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * This class is the interface to the legacy Module class.
 *
 * It will allow current modules to work even with the new ModuleManager
 */
class Module implements ModuleInterface
{
    /**
     * @var LegacyModule Module The instance of the legacy module
     */
    public $instance = null;

    /**
     * Module attributes (name, displayName etc.).
     *
     * @var ParameterBag
     */
    public $attributes;

    /**
     * Module attributes from disk.
     *
     * @var ParameterBag
     */
    public $disk;

    /**
     * Module attributes from database.
     *
     * @var ParameterBag
     */
    public $database;

    /**
     * Default values for ParameterBag attributes.
     *
     * @var array
     */
    protected $attributes_default = [
        'id' => 0,
        'name' => '',
        'picos' => [],
        'categoryName' => '',
        'displayName' => '',
        'version' => null,
        'description' => '',
        'author' => '',
        'author_uri' => false,
        'tab' => 'others',
        'is_configurable' => 0,
        'need_instance' => 0,
        'limited_countries' => [],
        'parent_class' => 'Module',
        'is_paymentModule' => false,
        'product_type' => 'module',
        'warning' => '',
        'img' => '',
        'badges' => [],
        'cover' => [],
        'screenshotsUrls' => [],
        'videoUrl' => null,
        'refs' => ['unknown'],
        'price' => [
            'EUR' => 0,
            'USD' => 0,
            'GBP' => 0,
        ],
        'type' => '',
        // From the marketplace
        'url' => null,
        'avgRate' => 0,
        'nbRates' => 0,
        'fullDescription' => '',
        'confirmUninstall' => '',
        // Generate addons urls
        'url_active' => null,
        'urls' => [],
    ];

    /**
     * Default values for ParameterBag disk.
     *
     * @var array
     */
    protected $disk_default = [
        'filemtype' => 0,
        'is_present' => 0,
        'is_valid' => 0,
        'version' => null,
        'path' => '',
    ];

    /**
     * Default values for ParameterBag database.
     *
     * @var array
     */
    protected $database_default = [
        'installed' => 0,
        'active' => 0,
        'active_on_mobile' => true,
        'version' => null,
        'last_access_date' => '0000-00-00 00:00:00',
        'date_add' => null,
        'date_upd' => null,
    ];

    /**
     * @param array|null $attributes
     * @param array|null $disk
     * @param array|null $database
     */
    public function __construct(?array $attributes = null, ?array $disk = null, ?array $database = null)
    {
        $this->attributes = new ParameterBag($this->attributes_default);
        $this->disk = new ParameterBag($this->disk_default);
        $this->database = new ParameterBag($this->database_default);

        // Set all attributes
        if ($attributes !== null) {
            $this->attributes->add($attributes);
        }

        if ($disk !== null) {
            $this->disk->add($disk);
        }

        if ($database !== null) {
            $this->database->add($database);
        }

        if ($this->database->get('installed')) {
            $version = $this->database->get('version');
        } elseif (null === $this->attributes->get('version') && $this->disk->get('is_valid')) {
            $version = $this->disk->get('version');
        } else {
            $version = $this->attributes->get('version');
        }

        if (!$this->attributes->has('version_available')) {
            $this->attributes->set('version_available', $this->disk->get('version'));
        }

        $this->fillLogo();

        $this->attributes->set('version', $version);
        $this->attributes->set('type', $this->convertType($this->get('origin_filter_value')));

        // Unfortunately, we can sometime have an array, and sometimes an object.
        // This is the first place where this value *always* exists
        $this->attributes->set('price', (array) $this->attributes->get('price'));
    }

    /**
     * @return LegacyModule|void
     *
     * @throws Exception
     */
    public function getInstance()
    {
        if (!$this->hasValidInstance()) {
            return;
        }

        return $this->instance;
    }

    /**
     * @return bool True if valid Module instance
     */
    public function hasValidInstance(): bool
    {
        if (($this->disk->has('is_present') && $this->disk->getBoolean('is_present') === false)
            || ($this->disk->has('is_valid') && $this->disk->getBoolean('is_valid') === false)
        ) {
            return false;
        }

        if ($this->instance === null) {
            // We try to instantiate the legacy class if not done yet
            try {
                $this->instantiateLegacyModule();
            } catch (Exception $e) {
                $this->disk->set('is_valid', false);

                return false;
            }
        }

        $this->disk->set('is_valid', $this->instance instanceof LegacyModule);

        return $this->disk->get('is_valid');
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return (bool) $this->database->get('active');
    }

    /**
     * {@inheritdoc}
     */
    public function onInstall(): bool
    {
        if (!$this->hasValidInstance()) {
            return false;
        }

        // If not modified, code used in installer is executed:
        // "Notice: Use of undefined constant _PS_INSTALL_LANGS_PATH_ - assumed '_PS_INSTALL_LANGS_PATH_'"
        LegacyModule::updateTranslationsAfterInstall(false);

        // Casted to Boolean, because some modules returns 1 instead true and 0 instead false.
        // Other value types are not expected. See also: https://github.com/PrestaShop/PrestaShop/pull/11442#issuecomment-440485268
        // The best way is to check for non Boolean type and `throw \UnexpectedValueException`,
        // but it's need much refactoring and testing.
        // TODO: refactoring.
        $result = (bool) $this->instance->install();

        $this->database->set('installed', $result);
        $this->database->set('active', $result);
        $this->database->set('version', $this->attributes->get('version'));

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function onPostInstall(): bool
    {
        if (!$this->hasValidInstance()) {
            return false;
        }

        return $this->instance->postInstall();
    }

    /**
     * {@inheritdoc}
     */
    public function onUninstall(): bool
    {
        if (!$this->hasValidInstance()) {
            return false;
        }

        $result = $this->instance->uninstall();
        $this->database->set('installed', !$result);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function onUpgrade($version): bool
    {
        $this->database->set('version', $this->attributes->get('version_available'));

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function onEnable(): bool
    {
        if (!$this->hasValidInstance()) {
            return false;
        }

        $result = $this->instance->enable();
        $this->database->set('active', $result);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function onDisable(): bool
    {
        if (!$this->hasValidInstance()) {
            return false;
        }

        $result = $this->instance->disable();
        $this->database->set('active', !$result);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function onMobileEnable(): bool
    {
        if (!$this->hasValidInstance()) {
            return false;
        }

        $result = $this->instance->enableDevice(Filters\Device::MOBILE);
        $this->database->set('active_on_mobile', $result);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function onMobileDisable(): bool
    {
        if (!$this->hasValidInstance()) {
            return false;
        }

        $result = $this->instance->disableDevice(Filters\Device::MOBILE);
        $this->database->set('active_on_mobile', !$result);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function onReset(): bool
    {
        if (!$this->hasValidInstance()) {
            return false;
        }

        return is_callable([$this->instance, 'reset']) ? $this->instance->reset() : true;
    }

    /**
     * Retrieve an instance of Legacy Module Object model from data.
     */
    protected function instantiateLegacyModule(): void
    {
        /**
         * @TODO Temporary: This test prevents an error when switching branches with the cache.
         * Can be removed at the next release (when we will be sure that it is defined)
         */
        $path = $this->disk->get('path', ''); // Variable needed for empty() test
        if (empty($path)) {
            $this->disk->set('path', _PS_MODULE_DIR_ . DIRECTORY_SEPARATOR . $this->attributes->get('name'));
        }
        // End of temporary content
        require_once $this->disk->get('path') . DIRECTORY_SEPARATOR . $this->attributes->get('name') . '.php';
        $this->instance = LegacyModule::getInstanceByName($this->attributes->get('name'));
    }

    /**
     * @param string $attribute
     *
     * @return mixed
     */
    public function get(string $attribute)
    {
        return $this->attributes->get($attribute, null);
    }

    /**
     * @param string $attribute
     * @param mixed $value
     */
    public function set(string $attribute, $value): void
    {
        $this->attributes->set($attribute, $value);
    }

    /**
     * @param string $value
     *
     * @return string
     */
    protected function convertType(string $value): string
    {
        $conversionTable = [
            Filters\Origin::ADDONS_CUSTOMER => 'addonsBought',
            Filters\Origin::ADDONS_MUST_HAVE => 'addonsMustHave',
        ];

        return $conversionTable[$value] ?? '';
    }

    /**
     * Set the module logo.
     */
    public function fillLogo(): void
    {
        $img = $this->attributes->get('img');
        if (empty($img)) {
            $this->attributes->set('img', __PS_BASE_URI__ . 'img/questionmark.png');
        }
        $this->attributes->set('logo', __PS_BASE_URI__ . 'img/questionmark.png');

        foreach (['logo.png', 'logo.gif'] as $logo) {
            $logo_path = _PS_MODULE_DIR_ . $this->get('name') . DIRECTORY_SEPARATOR . $logo;
            if (file_exists($logo_path)) {
                $this->attributes->set('img', __PS_BASE_URI__ . basename(_PS_MODULE_DIR_) . '/' . $this->get('name') . '/' . $logo);
                $this->attributes->set('logo', $logo);

                break;
            }
        }
    }

    /**
     * Inform the merchant an upgrade is waiting to be applied from the disk or the marketplace.
     *
     * @return bool
     */
    public function canBeUpgraded(): bool
    {
        if ($this->database->get('installed') == 0) {
            return false;
        }

        // Potential update from API
        if ($this->canBeUpgradedFromAddons()) {
            return true;
        }

        // Potential update from disk
        return version_compare($this->database->get('version'), $this->disk->get('version'), '<');
    }

    /**
     * Only check if an upgrade is available on the marketplace.
     *
     * @return bool
     */
    public function canBeUpgradedFromAddons(): bool
    {
        return $this->attributes->get('version_available') !== 0
            && version_compare($this->database->get('version'), $this->attributes->get('version_available'), '<');
    }

    /**
     * Return installed modules.
     *
     * @param int $position Take only positionable modules
     *
     * @return array Modules
     */
    public function getModulesInstalled(int $position = 0): array
    {
        return LegacyModule::getModulesInstalled($position);
    }

    /**
     * Return an instance of the specified module.
     *
     * @param int $moduleId Module id
     *
     * @return Module|false
     */
    public function getInstanceById(int $moduleId)
    {
        return LegacyModule::getInstanceById($moduleId);
    }
}
