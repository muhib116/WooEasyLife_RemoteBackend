<?php

return [
    /*
    | WordPress.org listing for EasyLife Courier Check.
    | Set COURIER_CHECK_WPORG_LIVE=false before config:cache until the plugin is public.
    | filter_var keeps the string "false" false. A plain (bool) cast would not.
    */
    'wporg_live' => filter_var(env('COURIER_CHECK_WPORG_LIVE', false), FILTER_VALIDATE_BOOLEAN),
];
