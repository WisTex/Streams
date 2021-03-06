<?php

namespace Code\Module;

use Code\Web\Controller;

class Pretheme extends Controller
{

    public function init()
    {

        if ($_REQUEST['theme']) {
            $theme = $_REQUEST['theme'];
            $info = get_theme_info($theme);
            if ($info) {
                // unfortunately there will be no translation for this string
                $desc = $info['description'];
                $version = $info['version'];
                $credits = $info['credits'];
            } else {
                $desc = '';
                $version = '';
                $credits = '';
            }
            echo json_encode(array('img' => get_theme_screenshot($theme), 'desc' => $desc, 'version' => $version, 'credits' => $credits));
        }
        killme();
    }
}
