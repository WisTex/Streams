<?php

namespace Code\Module;

use Code\Web\Controller;

require_once('include/security.php');
require_once('include/attach.php');


class Attach extends Controller
{

    public function init()
    {

        if (argc() < 2) {
            notice(t('Item not available.') . EOL);
            return;
        }

        $r = attach_by_hash(argv(1), get_observer_hash(), ((argc() > 2) ? intval(argv(2)) : 0));

        if (!$r['success']) {
            notice($r['message'] . EOL);
            return;
        }

        $c = q(
            "select channel_address from channel where channel_id = %d limit 1",
            intval($r['data']['uid'])
        );

        if (!$c) {
            return;
        }

        header('Content-type: ' . $r['data']['filetype']);
        header('Content-Disposition: attachment; filename="' . $r['data']['filename'] . '"');
        if (intval($r['data']['os_storage'])) {
            $fname = dbunescbin($r['data']['content']);
            if (strpos($fname, 'store') !== false) {
                $istream = fopen($fname, 'rb');
            } else {
                $istream = fopen('store/' . $c[0]['channel_address'] . '/' . $fname, 'rb');
            }
            $ostream = fopen('php://output', 'wb');
            if ($istream && $ostream) {
                pipe_streams($istream, $ostream);
                fclose($istream);
                fclose($ostream);
            }
        } else {
            echo dbunescbin($r['data']['content']);
        }
        killme();
    }
}
