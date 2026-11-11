<?php

namespace App\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;

class SeedDemo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:demo-seed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed the demo workspace regardless of the environment.';

    public function handle(): int
    {
        $this->app['env'] = 'local';
        $this->call(DatabaseSeeder::class);

        return self::SUCCESS;
    }
}
