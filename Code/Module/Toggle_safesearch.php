<?php

namespace Code\Module;

use Code\Web\Controller;

class Toggle_safesearch extends Controller
{

    public function init()
    {

        $observer = get_observer_hash();
        if (!$observer) {
            return;
        }

        if ($observer) {
            $safe_mode = get_xconfig($observer, 'directory', 'safe_mode');
        }
        if ($safe_mode == '') {
            set_xconfig($observer, 'directory', 'safe_mode', '0');
        } elseif ($safe_mode == '0') {
            set_xconfig($observer, 'directory', 'safe_mode', '1');
        } elseif ($safe_mode == '1') {
            set_xconfig($observer, 'directory', 'safe_mode', '0');
        }

        if (isset($_GET['address'])) {
            $address = $_GET['address'];
        } else {
            $address = z_root() . '/directory';
        }

        goaway($address);
    }
}
