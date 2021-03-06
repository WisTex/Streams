<?php

namespace Code\Module;

use App;
use Code\Lib\Libprofile;
use Code\Web\Controller;
use Code\Render\Theme;
use Code\Lib\Socgraph;


class Common extends Controller
{

    public function init()
    {

        if (argc() > 1 && intval(argv(1))) {
            $channel_id = intval(argv(1));
        } else {
            notice(t('No channel.') . EOL);
            App::$error = 404;
            return;
        }

        $x = q(
            "select channel_address from channel where channel_id = %d limit 1",
            intval($channel_id)
        );

        if ($x) {
            Libprofile::load($x[0]['channel_address'], 0);
        }
    }

    public function get()
    {

        $o = '';

        if (!App::$profile['profile_uid']) {
            return;
        }

        $observer_hash = get_observer_hash();

        if (!perm_is_allowed(App::$profile['profile_uid'], $observer_hash, 'view_contacts')) {
            notice(t('Permission denied.') . EOL);
            return;
        }

        $t = Socgraph::count_common_friends(App::$profile['profile_uid'], $observer_hash);

        if (!$t) {
            notice(t('No connections in common.') . EOL);
            return;
        }

        $r = Socgraph::common_friends(App::$profile['profile_uid'], $observer_hash);

        if ($r) {
            foreach ($r as $rr) {
                $items[] = [
                    'url' => $rr['xchan_url'],
                    'name' => $rr['xchan_name'],
                    'photo' => $rr['xchan_photo_m'],
                    'tags' => ''
                ];
            }
        }

        $tpl = Theme::get_template('common_friends.tpl');

        $o = replace_macros($tpl, [
            '$title' => t('View Common Connections'),
            '$items' => $items
        ]);

        return $o;
    }
}
