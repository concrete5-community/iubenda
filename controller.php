<?php

namespace Concrete\Package\Iubenda;

use Concrete\Core\Asset\AssetInterface;
use Concrete\Core\Asset\AssetList;
use Concrete\Core\Database\EntityManager\Provider\ProviderInterface;
use Concrete\Core\Package\Package;

defined('C5_EXECUTE') or die('Access denied.');

class Controller extends Package implements ProviderInterface
{
    /**
     * The package handle.
     *
     * @var string
     */
    protected $pkgHandle = 'iubenda';

    /**
     * The package version.
     *
     * @var string
     */
    protected $pkgVersion = '1.0.1';

    /**
     * The minimum concrete5 version.
     *
     * @var string
     */
    protected $appVersionRequired = '8.5.0';

    protected $packageDependencies = [
        'http_client_compat' => true,
    ];

    /**
     * Map folders to PHP namespaces, for automatic class autoloading.
     *
     * @var array
     */
    protected $pkgAutoloaderRegistries = [
        'src' => 'CCMIubenda',
    ];

    /**
     * {@inheritdoc}
     */
    public function getPackageName()
    {
        return t('Iubenda');
    }

    /**
     * {@inheritdoc}
     */
    public function getPackageDescription()
    {
        return t('Make it easier to integrate Iubenda into your website');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Database\EntityManager\Provider\ProviderInterface::getDrivers()
     */
    public function getDrivers()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::install()
     */
    public function install()
    {
        parent::install();
        $this->installContentFile('config/install.xml');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::upgrade()
     */
    public function upgrade()
    {
        parent::upgrade();
        $this->installContentFile('config/install.xml');
    }

    public function on_start()
    {
        $this->registerAssets();
    }

    private function registerAssets()
    {
        $al = AssetList::getInstance();
        $al->registerMultiple([
            'iubenda-ext' => [
                ['javascript', 'https://cdn.iubenda.com/iubenda.js', ['local' => false, 'minify' => false, 'combine' => false, 'position' => AssetInterface::ASSET_POSITION_FOOTER], $this],
            ],
        ]);
        $al->registerGroupMultiple([
            'iubenda-ext' => [
                [
                    ['javascript', 'iubenda-ext'],
                ],
            ],
        ]);            
    }
}
