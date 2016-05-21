<?php
/**
 * Created by PhpStorm.
 * User: thomasredstone
 * Date: 21/05/2016
 * Time: 20:35
 */

namespace RedstoneTechnology\ServerBuild\Utilities;

/**
 * Class Packages
 * @package RedstoneTechnology\ServerBuild\Utilities
 */
class Packages
{
    /**
     * @param $packages
     * @param bool $compact
     * @return bool|string
     */
    public function setupPackages($packages, $compact = false)
    {
        if (empty($packages)) {
            return false;
        }
        if (is_array($packages)) {
            $script = '';
            $packageList = '';
            foreach ($packages as $package) {
                if ($compact === true) {
                    $packageList .= "{$package} ";
                    continue;
                }
                $script .= $this->setupPackages($package, $compact);
            }
            if ($compact === true) {
                $script = $this->setupPackages($packageList, $compact)."\n";
            }
        } else {
            $script = $this->installPackage($packages, $compact);
        }
        return $script;
    }

    /**
     * @param $package
     * @return string
     */
    protected function installPackage($package)
    {
        if (!filter_var($package, FILTER_VALIDATE_URL) === false) {
            return str_replace('{package}', $package, $this->config['commands']['install']['remote'])."\n";
        }
        return str_replace('{package}', $package, $this->config['commands']['install']['package'])."\n";
    }
}
