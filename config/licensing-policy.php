<?php

/**
 * Default heartbeat policy values.
 *
 * These are the server-side defaults. Individual licenses can override
 * them by setting meta.policy.heartbeat_tolerance and meta.policy.warning_days.
 */
return [
    /*
     | Number of consecutive heartbeat failures before the client shows
     | a warning banner to the user.
     */
    'heartbeat_tolerance' => (int) env('LICENSE_HEARTBEAT_TOLERANCE', 3),

    /*
     | Number of days after the first heartbeat failure before the client
     | shows a full-screen modal that cannot be dismissed.
     */
    'warning_days' => (int) env('LICENSE_WARNING_DAYS', 3),
];
