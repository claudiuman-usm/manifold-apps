<?php

namespace Database\Seeders;

use App\Modules\Flower\Models\Client;
use App\Modules\Flower\Models\Run;
use App\Modules\Flower\Models\Step;
use App\Modules\Flower\Models\Template;
use App\Modules\Flower\Models\Type;
use App\Modules\Flower\Models\StepLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoFlowerSeeder extends Seeder
{
    public function run(): void
    {
        $client = Client::firstOrCreate(['name' => 'AcmeCorp']);
        $type = Type::firstOrCreate(['client_id' => $client->id, 'name' => 'Podcast']);
        $template = Template::firstOrCreate(['type_id' => $type->id, 'name' => 'Main edit']);

        $names = ['Import & sync', 'Rough cut', 'Color grade', 'Audio mix', 'Export'];
        $steps = [];
        foreach ($names as $i => $n) {
            $steps[] = Step::firstOrCreate(['template_id' => $template->id, 'name' => $n], ['position' => $i]);
        }

        if ($template->runs()->count() === 0) {
            foreach ([[38, 182, 140, 92, 45], [42, 205, 166, 80, 52]] as $durations) {
                $start = Carbon::now()->subDay();
                $run = Run::create([
                    'template_id' => $template->id,
                    'status' => 'completed',
                    'started_at' => $start,
                    'completed_at' => $start->copy()->addSeconds(array_sum($durations)),
                ]);
                $cursor = $start->copy();
                foreach ($steps as $i => $step) {
                    StepLog::create([
                        'run_id' => $run->id,
                        'step_id' => $step->id,
                        'started_at' => $cursor->copy(),
                        'completed_at' => $cursor->copy()->addSeconds($durations[$i]),
                        'duration_seconds' => $durations[$i],
                    ]);
                    $cursor->addSeconds($durations[$i]);
                }
            }
        }

        $type2 = Type::firstOrCreate(['client_id' => $client->id, 'name' => 'Reels']);
        $template2 = Template::firstOrCreate(['type_id' => $type2->id, 'name' => 'Quick cut']);
        foreach (['Trim', 'Captions', 'Export vertical'] as $i => $n) {
            Step::firstOrCreate(['template_id' => $template2->id, 'name' => $n], ['position' => $i]);
        }
    }
}
