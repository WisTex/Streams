<?php

namespace Zotlabs\Lib;

use App;

class Addon {

    /**    
     * @brief Handle errors in plugin calls
     *
     * @param string $addon name of the addon
     * @param string $error_text text of error
     * @param bool $uninstall uninstall plugin
     */
    public static function ErrorHandler($addon, $notice, $log, $uninstall = false)
    {
        logger("Addons: [" . $addon . "] Error: ".$log, LOGGER_ERROR);
        if ($notice != '') {
            notice("[" . $addon . "] Error: ".$notice, LOGGER_ERROR);
        }

        if ($uninstall) {
            self::uninstall($addon);
        }
    }

    /**
     * @brief Unloads an addon.
     *
     * @param string $addon name of the addon
     */
    public static function unload($addon)
    {
        logger("Addons: unloading " . $addon, LOGGER_DEBUG);

        @include_once('addon/' . $addon . '/' . $addon . '.php');
        if (function_exists($addon . '_unload')) {
            $func = $addon . '_unload';
            try {
                $func();
            } catch (Exception $e) {
                self::ErrorHandler($addon, "Unable to unload.", $e->getMessage());
            }
        }
    }

    /**
     * @brief Uninstalls an addon.
     *
     * @param string $addon name of the addon
     * @return bool
     */
    public static  function uninstall($addon)
    {

        self::unload($addon);

        if (! file_exists('addon/' . $addon . '/' . $addon . '.php')) {
            q(
                "DELETE FROM addon WHERE aname = '%s' ",
                dbesc($addon)
            );
            return false;
        }

        logger("Addons: uninstalling " . $addon);
        //$t = @filemtime('addon/' . $addon . '/' . $addon . '.php');
        @include_once('addon/' . $addon . '/' . $addon . '.php');
        if (function_exists($addon . '_uninstall')) {
            $func = $addon . '_uninstall';
            try {
                $func();
            } catch (Exception $e) {
                self::ErrorHandler($addon, "Unable to uninstall.", "Unable to run _uninstall : ".$e->getMessage());
            }
        }

        q(
            "DELETE FROM addon WHERE aname = '%s' ",
            dbesc($addon)
        );
    }

    /**
     * @brief Installs an addon.
     *
     * This function is called once to install the addon (either from the cli or via
     * the web admin). This will also call load_plugin() once.
     *
     * @param string $addon name of the addon
     * @return bool
     */
    public static function install($addon)
    {
        if (! file_exists('addon/' . $addon . '/' . $addon . '.php')) {
            return false;
        }

        logger("Addons: installing " . $addon);
        $t = @filemtime('addon/' . $addon . '/' . $addon . '.php');
        @include_once('addon/' . $addon . '/' . $addon . '.php');
        if (function_exists($addon . '_install')) {
            $func = $addon . '_install';
            try {
                $func();
            } catch (Exception $e) {
                self::ErrorHandler($addon, "Install failed.", "Install failed : ".$e->getMessage());
                return;
            }
        }

        $addon_admin = (function_exists($addon . '_plugin_admin') ? 1 : 0);

        $d = q(
            "select * from addon where aname = '%s' limit 1",
            dbesc($addon)
        );
        if (! $d) {
            q(
                "INSERT INTO addon (aname, installed, tstamp, plugin_admin) VALUES ( '%s', 1, %d , %d ) ",
                dbesc($addon),
                intval($t),
                $addon_admin
            );
        }

        self::load($addon);
    }

    /**
     * @brief loads an addon by it's name.
     *
     * @param string $addon name of the addon
     * @return bool
     */
    public static  function load($addon)
    {
        // silently fail if plugin was removed
        if (! file_exists('addon/' . $addon . '/' . $addon . '.php')) {
            return false;
        }

        logger("Addons: loading " . $addon, LOGGER_DEBUG);
        //$t = @filemtime('addon/' . $addon . '/' . $addon . '.php');
        @include_once('addon/' . $addon . '/' . $addon . '.php');
        if (function_exists($addon . '_load')) {
            $func = $addon . '_load';
            try {
                $func();
            } catch (Exception $e) {
                self::ErrorHandler($addon, "Unable to load.", "FAILED loading : ".$e->getMessage(), true);
                return;
            }

            // we can add the following with the previous SQL
            // once most site tables have been updated.
            // This way the system won't fall over dead during the update.

            if (file_exists('addon/' . $addon . '/.hidden')) {
                q(
                    "update addon set hidden = 1 where name = '%s'",
                    dbesc($addon)
                );
            }
            return true;
        } else {
            logger("Addons: FAILED loading " . $addon . " (missing _load function)");
            return false;
        }
    }

    /**
     * @brief Check if addon is installed.
     *
     * @param string $name
     * @return bool
     */

    public static function is_installed($name)
    {
        $r = q(
            "select aname from addon where aname = '%s' and installed = 1 limit 1",
            dbesc($name)
        );
        if ($r) {
            return true;
        }

        return false;
    }


    /**
     * @brief Reload all updated plugins.
     */
    public static  function reload_all()
    {
        $addons = get_config('system', 'addon');
        if (strlen($addons)) {
            $r = q("SELECT * FROM addon WHERE installed = 1");
            if (count($r)) {
                $installed = $r;
            } else {
                $installed = [];
            }

            $parr = explode(',', $addons);

            if (count($parr)) {
                foreach ($parr as $pl) {
                    $pl = trim($pl);

                    $fname = 'addon/' . $pl . '/' . $pl . '.php';

                    if (file_exists($fname)) {
                        $t = @filemtime($fname);
                        foreach ($installed as $i) {
                            if (($i['aname'] == $pl) && ($i['tstamp'] != $t)) {
                                logger('Reloading plugin: ' . $i['aname']);
                                @include_once($fname);

                                if (function_exists($pl . '_unload')) {
                                    $func = $pl . '_unload';
                                    try {
                                            $func();
                                    } catch (Exception $e) {
                                        self::ErrorHandler($addon, "", "UNLOAD FAILED (uninstalling) : ".$e->getMessage(), true);
                                                                            continue;
                                    }
                                }
                                if (function_exists($pl . '_load')) {
                                    $func = $pl . '_load';
                                    try {
                                            $func();
                                    } catch (Exception $e) {
                                        self::ErrorHandler($addon, "", "LOAD FAILED (uninstalling): ".$e->getMessage(), true);
                                                                            continue;
                                    }
                                }
                                q(
                                    "UPDATE addon SET tstamp = %d WHERE id = %d",
                                    intval($t),
                                    intval($i['id'])
                                );
                            }
                        }
                    }
                }
            }
        }
    }


    public static function list_installed()
    {

        $r = q("select * from addon where installed = 1 order by aname asc");
        return(($r) ? ids_to_array($r, 'aname') : []);
    }


    /**
     * @brief Get a list of non hidden addons.
     *
     * @return array
     */
    public static function list_visible()
    {
        
        $r = q("select * from addon where hidden = 0 order by aname asc");
        $x = (($r) ? ids_to_array($r, 'aname') : []);
        $y = [];
        if ($x) {
            foreach ($x as $xv) {
                if (is_dir('addon/' . $xv)) {
                    $y[] = $xv;
                }
            }
        }
        return $y;
    }


}