<?php
namespace Zotlabs\Module;

use Zotlabs\Lib\Libsync;
use Zotlabs\Web\Controller;

class Starred extends Controller
{

    public function init()
    {

        if (!local_channel()) {
            killme();
        }
        $message_id = ((argc() > 1) ? intval(argv(1)) : 0);
        if (!$message_id) {
            killme();
        }

        $r = q(
            "SELECT item_starred FROM item WHERE uid = %d AND id = %d LIMIT 1",
            intval(local_channel()),
            intval($message_id)
        );
        if (!count($r)) {
            killme();
        }

        $item_starred = (intval($r[0]['item_starred']) ? 0 : 1);

        $r = q(
            "UPDATE item SET item_starred = %d WHERE uid = %d and id = %d",
            intval($item_starred),
            intval(local_channel()),
            intval($message_id)
        );

        $r = q(
            "select * from item where id = %d",
            intval($message_id)
        );
        if ($r) {
            xchan_query($r);
            $sync_item = fetch_post_tags($r);
            Libsync::build_sync_packet(local_channel(), [
                'item' => [
                    encode_item($sync_item[0], true)
                ]
            ]);
        }

        header('Content-type: application/json');
        echo json_encode(array('result' => $item_starred));
        killme();
    }
}
