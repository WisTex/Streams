<?php

namespace Code\Module;

use Code\Lib\Apps;
use Code\Lib\Libsync;
use Code\Web\Controller;

class Clients extends Controller
{


    public function get()
    {

        $desc = t('This app allows you to authorize mobile apps using OAuth and OpenID to access your channel.');

        if (!Apps::system_app_installed(local_channel(), 'Clients')) {
            return '<div class="section-content-info-wrapper">' . $desc . '</div>';
        }
        goaway(z_root() . '/settings/oauth2');
    }
}
