<?php
namespace Zotlabs\Module;

/*
 * Zotfinger
 * Diagnostic tool to perform Zot6 channel discovery
 * and optionally import the record (if invoked by site admin).
 * Also see zot_probe which provides similar functionality
 * for all types of Zot6 resources and is not restricted to
 * channel discovery.
 */
 
use App;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\Libzot;
use Zotlabs\Lib\Zotfinger as Zfinger;

class Zotfinger extends Controller
{

    public function get()
    {

        $o = replace_macros(get_markup_template('zotfinger.tpl'), [
            '$page_title' => t('Zotfinger Diagnostic'),
            '$resource' => ['resource', t('Lookup URL'), $_GET['resource'], EMPTY_STR],
            '$submit' => t('Submit')
        ]);

        if ($_GET['resource']) {
            $channel = App::get_channel();
            $resource = trim(escape_tags($_GET['resource']));
            $do_import = ((intval($_GET['import']) && is_site_admin()) ? true : false);

            $j = Zfinger::exec($resource, $channel);

            if ($do_import && $j) {
                $x = Libzot::import_xchan($j['data']);
            }
            $o .= '<pre>' . str_replace("\n", '<br>', print_array($j)) . '</pre>';
        }
        return $o;
    }

}
