<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        $subdomain = fake()->randomElement(['api', 'www', 'auth', 'admin', 'cdn', 'legacy', 'staging', 'dev', 'mail', 'docs', 'db-test', 'files', 'campus', 'news']);

        return [
            'asset_group_id' => AssetGroup::factory(),
            'hostname' => $subdomain.'.'.fake()->domainName(),
            'ip_address' => fake()->ipv4(),
            'http_status' => fake()->randomElement([200, 200, 200, 301, 403, 404, 500]),
            'title' => fake()->randomElement([null, null, 'Admin Panel', 'Login', 'Index of /', 'Welcome']),
            'server_header' => fake()->randomElement(['nginx', 'nginx', 'Apache', 'cloudflare', 'varnish', 'Microsoft-IIS/10.0']),
            'cdn' => fake()->randomElement([null, null, 'cloudflare', 'cloudflare', 'fastly']),
            'technologies' => fake()->randomElement([
                ['nginx', 'php'],
                ['nginx'],
                ['apache', 'php', 'wordpress'],
                ['node.js', 'express'],
                ['cloudflare'],
                ['iis', 'asp.net'],
            ]),
            'ports' => fake()->randomElement([
                [80, 443],
                [80, 443, 22],
                [80, 443, 3306],
                [80, 443, 8080],
            ]),
            'screenshot_seed' => fake()->numberBetween(1, 9999),
            'first_seen_at' => fake()->dateTimeBetween('-90 days', '-30 days'),
            'last_seen_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ];
    }
}
