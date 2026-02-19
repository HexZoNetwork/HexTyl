<?php

namespace Pterodactyl\Http\Controllers\Api;

use Illuminate\Http\Request;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Security\BehavioralScoreService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class HoneypotController extends Controller
{
    public function __construct(private BehavioralScoreService $riskService)
    {
    }

    /**
     * Setup a bait endpoint.
     * If this is hit, it's definitely a bot or malicious scanner.
     */
    public function index(Request $request)
    {
        $ip = $request->ip();
        
        // Instant penalty
        $this->riskService->incrementRisk($ip, 'honeypot_hit');

        // Log it
        \Log::warning("Honeypot hit by IP: {$ip}");

        // Return a generic error to confuse the scanner
        throw new AccessDeniedHttpException('System error: 0xDEADBEEF');
    }
}
