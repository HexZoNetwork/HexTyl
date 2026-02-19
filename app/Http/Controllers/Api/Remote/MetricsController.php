<?php

namespace Pterodactyl\Http\Controllers\Api\Remote;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller
{
    /**
     * Expose metrics for Prometheus.
     * Route: GET /metrics
     */
    public function index()
    {
        $usersDetails = [
            'total' => User::count(),
            'admins' => User::where('root_admin', 1)->count(),
        ];

        $serversDetails = [
            'total' => Server::count(),
            'suspended' => Server::where('status', 'suspended')->count(),
        ];
        
        // Format as Prometheus text
        $output = "";
        
        $output .= "# HELP pterodactyl_users_total Total number of users\n";
        $output .= "# TYPE pterodactyl_users_total gauge\n";
        $output .= "pterodactyl_users_total {$usersDetails['total']}\n";
        
        $output .= "# HELP pterodactyl_users_admins Total number of admins\n";
        $output .= "# TYPE pterodactyl_users_admins gauge\n";
        $output .= "pterodactyl_users_admins {$usersDetails['admins']}\n";

        $output .= "# HELP pterodactyl_servers_total Total number of servers\n";
        $output .= "# TYPE pterodactyl_servers_total gauge\n";
        $output .= "pterodactyl_servers_total {$serversDetails['total']}\n";

        $output .= "# HELP pterodactyl_servers_suspended Total number of suspended servers\n";
        $output .= "# TYPE pterodactyl_servers_suspended gauge\n";
        $output .= "pterodactyl_servers_suspended {$serversDetails['suspended']}\n";

        return response($output, 200, ['Content-Type' => 'text/plain']);
    }
}
