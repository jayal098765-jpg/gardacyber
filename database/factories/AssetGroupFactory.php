<?php

namespace Database\Factories;

use App\Models\AssetGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetGroup>
 */
class AssetGroupFactory extends Factory
{
    protected $model = AssetGroup::class;

    public function definition(): array
    {
        $domain = fake()->unique()->domainName();

        return [
            'user_id' => User::factory(),
            'root_domain' => $domain,
            'slug' => str($domain)->replace('.', '-')->toString(),
            'single_host' => false,
            'last_scanned_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ];
    }
}
