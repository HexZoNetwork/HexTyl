<?php

namespace Pterodactyl\Services\Servers;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Nest;
use Illuminate\Support\Collection;

class TemplateService
{
    /**
     * Get available templates.
     */
    public function getTemplates(): Collection
    {
        return collect([
            [
                'id' => 'discord-js',
                'name' => 'Discord Bot (Node.js)',
                'egg_id' => 1, // Assuming generic Node egg
                'docker_image' => 'ghcr.io/parkervcp/yolks:nodejs_18',
                'startup' => 'node index.js',
            ],
            [
                'id' => 'telegram-python',
                'name' => 'Telegram Bot (Python)',
                'egg_id' => 2, // Assuming generic Python egg
                'docker_image' => 'ghcr.io/parkervcp/yolks:python_3.10',
                'startup' => 'python3 main.py',
            ],
            [
                'id' => 'express-api',
                'name' => 'Express API Starter',
                'egg_id' => 1,
                'docker_image' => 'ghcr.io/parkervcp/yolks:nodejs_18',
                'startup' => 'npm start',
            ]
        ]);
    }

    /**
     * Apply a template to server data.
     */
    public function applyTemplate(string $templateId, array &$data): void
    {
        $template = $this->getTemplates()->firstWhere('id', $templateId);

        if (!$template) {
            return;
        }

        // Override or fill defaults
        $data['egg_id'] = $template['egg_id'];
        $data['image'] = $template['docker_image'];
        $data['startup'] = $template['startup'];
        
        // In a real implementation, we would also verify Nest IDs and fetch environment variables
    }
}
