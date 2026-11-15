<?php

namespace App\Modules\PodPlan\Support;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Modules\PodPlan\Models\GearItem;
use App\Modules\PodPlan\Models\Project;
use Illuminate\Support\Facades\Log;

/**
 * Turns a description of a shoot into placements, using the Claude API.
 *
 * The model only ever sees the plan gear's id, name and type (never who an
 * item is rented from) and answers with structured JSON. It is told to use
 * inventory ids only; PlanService::applyLayout() enforces that again, so gear
 * the owner does not have can never reach the plan - it comes back in
 * `missing` instead.
 */
class PlanDescriber
{
    public function enabled(): bool
    {
        return ! empty(config('podplan.api_key'));
    }

    /**
     * @param  list<array<string,mixed>>  $current  placements on the plan right now
     * @return array{ok:bool,error?:string,placements?:list<array<string,mixed>>,missing?:list<string>,notes?:string}
     */
    public function describe(Project $project, string $instruction, array $current): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'error' => 'no_key'];
        }

        $inventory = GearItem::with('type')->orderBy('sort')->orderBy('id')->get()
            ->filter(fn (GearItem $i) => $i->type->isPlan())
            ->map(fn (GearItem $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'type' => $i->type->name,
                'units' => max(1, (int) $i->quantity),
                'rented' => ! $i->isMine(),
            ])->values()->all();

        $request = [
            'shoot' => [
                'type' => $project->shoot_type,
                'people_on_camera' => (int) $project->people_count,
                'live_mixing' => (bool) $project->live_mixing,
            ],
            'request' => $instruction,
            'inventory' => $inventory,
            'current_plan' => $current,
        ];

        try {
            $client = new Client(apiKey: config('podplan.api_key'));

            // Server-side fallbacks: if the model declines for policy reasons the
            // API retries on its default fallback model inside the same call.
            $message = $client->beta->messages->create(
                maxTokens: 16000,
                model: config('podplan.model', 'claude-opus-5-5'),
                system: $this->system(),
                messages: [[
                    'role' => 'user',
                    'content' => json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]],
                outputConfig: [
                    'effort' => 'medium',
                    'format' => ['type' => 'json_schema', 'schema' => $this->schema()],
                ],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
                requestOptions: ['timeout' => 120.0],
            );

            if ($message->stopReason === 'refusal') {
                return ['ok' => false, 'error' => 'refused'];
            }
            if ($message->stopReason === 'max_tokens') {
                return ['ok' => false, 'error' => 'too_long'];
            }

            foreach ($message->content as $block) {
                if (($block->type ?? null) === 'text') {
                    $data = json_decode($block->text, true);
                    if (is_array($data) && isset($data['placements']) && is_array($data['placements'])) {
                        return [
                            'ok' => true,
                            'placements' => $this->clean($data['placements']),
                            'missing' => array_slice(array_values(array_filter(array_map(
                                fn ($m) => trim((string) $m), (array) ($data['missing'] ?? [])
                            ))), 0, 20),
                            'notes' => trim((string) ($data['notes'] ?? '')),
                        ];
                    }
                }
            }

            return ['ok' => false, 'error' => 'bad_reply'];
        } catch (AuthenticationException $e) {
            Log::warning('PodPlan describe: authentication failed.');

            return ['ok' => false, 'error' => 'auth'];
        } catch (RateLimitException $e) {
            return ['ok' => false, 'error' => 'busy'];
        } catch (APIStatusException $e) {
            Log::warning('PodPlan describe failed: '.$e->getMessage());

            return ['ok' => false, 'error' => $e->status >= 500 ? 'busy' : 'failed'];
        } catch (APIConnectionException $e) {
            return ['ok' => false, 'error' => 'offline'];
        } catch (\Throwable $e) {
            Log::warning('PodPlan describe failed: '.$e->getMessage());

            return ['ok' => false, 'error' => 'failed'];
        }
    }

    /** @return list<array<string,mixed>> */
    protected function clean(array $placements): array
    {
        $out = [];
        $people = 0;

        foreach ($placements as $p) {
            if (! is_array($p)) {
                continue;
            }
            $kind = (string) ($p['kind'] ?? '');
            if (! in_array($kind, ['gear', 'person', 'furniture'], true)) {
                continue;
            }
            if ($kind === 'person' && ++$people > 8) {
                continue;
            }
            $out[] = [
                'kind' => $kind,
                'gear_item_id' => $kind === 'gear' ? (int) ($p['gear_item_id'] ?? 0) : null,
                'label' => trim((string) ($p['label'] ?? '')),
                'x' => (float) ($p['x'] ?? 0),
                'y' => (float) ($p['y'] ?? 0),
                'rotation' => $kind === 'gear' ? (float) ($p['rotation'] ?? 0) : 0.0,
            ];
        }

        return $out;
    }

    protected function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'placements' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'kind' => ['type' => 'string', 'enum' => ['gear', 'person', 'furniture']],
                            'gear_item_id' => ['type' => 'integer', 'description' => 'Inventory id when kind is gear; 0 otherwise.'],
                            'label' => ['type' => 'string'],
                            'x' => ['type' => 'number'],
                            'y' => ['type' => 'number'],
                            'rotation' => ['type' => 'number'],
                        ],
                        'required' => ['kind', 'gear_item_id', 'label', 'x', 'y', 'rotation'],
                        'additionalProperties' => false,
                    ],
                ],
                'missing' => ['type' => 'array', 'items' => ['type' => 'string']],
                'notes' => ['type' => 'string'],
            ],
            'required' => ['placements', 'missing', 'notes'],
            'additionalProperties' => false,
        ];
    }

    protected function system(): string
    {
        [$zx, $zy, $zw, $zh] = PlanService::MIXER_ZONE;

        return strtr(<<<'TXT'
You lay out the top-down plan of a video shoot for a videographer. You receive the shoot settings, a request in the videographer's own words, the gear they own, and what is on the plan right now. Reply with the complete new plan: every symbol that should be on it afterwards, not just the changes.

The canvas is {W} wide and {H} high. The origin is the top-left corner and y grows downward. x and y are the top-left corner of a symbol. Symbol sizes, width by height: camera 40x28, microphone (wired or wireless) 16x16, light 34x34, video mixer and other boxes {BOX}, audio mixer {REC}, person 32x32, table 200x80. Keep every symbol fully inside the canvas, leave about 20 units under each one for its label, and do not overlap symbols.

How a set is usually arranged: the table and the people sit around the middle of the canvas. Cameras sit toward the bottom and look up at the set. Lights go to the sides and the top. The bottom-right corner is FOH, the mixer corner, from x {ZX1} to {ZX2} and y {ZY1} to {ZY2}: every video mixer and audio mixer goes inside it and nothing else does. Stack them one under another, 22 apart, starting at x {ZMX}, y {ZMY}, each one 36 further right than the one above; their labels are drawn to their right. Cables are drawn along the outer edge of the canvas, so keep the outermost 40 units of every side free of other gear where you can. Cameras and lights have a rotation in degrees clockwise, where 0 points straight up, toward smaller y. Aim each camera at what it should frame and each light at what it should light. Every other symbol has rotation 0. A wired microphone goes on the table in front of the person using it; a wireless one sits right next to the person wearing it.

Gear comes only from the inventory, by its id. An id can be placed as many times as its "units": most gear has one, while a wireless microphone kit with 2 units is two transmitters, one for each person wearing one. Never invent gear. When the request asks for something that is not in the inventory, or for more units than there are, leave it out and name it in "missing" in a few words. Prefer the videographer's own gear over rented gear when either would do. If live mixing is on and the inventory has a video mixer, place one. Place what the request asks for or clearly implies, and nothing extra. When the request only changes part of the plan, keep the rest where it is.

People are kind "person" with a short label such as Host or Guest 1; match the people count unless the request says otherwise. A table is kind "furniture" with the label "table"; tables are the only furniture that is drawn. For people and furniture gear_item_id is 0. For gear, the label is the item's name.

"notes" is one or two plain sentences saying what you placed and any judgement call you made, written in the language of the request.
TXT, [
            '{W}' => PlanService::W, '{H}' => PlanService::H,
            '{ZX1}' => $zx, '{ZX2}' => $zx + $zw, '{ZY1}' => $zy, '{ZY2}' => $zy + $zh, '{ZMY}' => $zy + 18, '{ZMX}' => $zx + 10,
            '{BOX}' => implode('x', PlanService::SIZES['box']), '{REC}' => implode('x', PlanService::SIZES['recorder']),
        ]);
    }
}
