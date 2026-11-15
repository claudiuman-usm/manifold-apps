# PodPlan

Plan a shoot on a top-down diagram and pack from the checklist it builds.
Lives at `/podplan`; auto-discovered by the hub like every other module.

## What it does

1. **Project** — name, shoot type, date, location, people on camera, live mixing yes/no.
2. **Plan** — tap gear from the inventory onto a 660 x 560 top-down canvas, drag it, turn cameras. Or describe the shoot and let Claude place the gear.
3. **Checklist** — generated from the gear on the plan and the live-mixing choice. Ticked only on the Checklist screen (the Plan shows a read-only preview).
4. **Pack together** — a secret link so colleagues can tick the same list with no login.

## Two kinds of inventory

| Kind | Examples | On the plan? | Counted? |
|---|---|---|---|
| **Plan gear** | cameras, mixers, mics, lights | yes, one placement per piece | no — two identical lights are two items. `quantity` is the number of *pieces* placed separately: 1 for almost everything, 2 for a wireless kit with two lavs |
| **Accessories** | tripods, light stands, SSDs, lenses, adapters, monitors, FetHeads, timecode | never | yes — a pool with a quantity |

Every item has a provider: empty means it is yours, otherwise the name it is
rented from. Rented items carry that tag on the checklist.

## Rules

Each gear type (a "kind" in the UI) has its own rules, seeded with a preset
and editable from Inventory; an item can override them (stored in
`rule_overrides`, `null` = follow the kind). "Use for every ..." on an item
makes its rules the kind's and brings every item of the kind back in line.
Four buckets:

- `always` — added whenever the gear is on the plan
- `live` — only when live mixing is on
- `not_live` — only when live mixing is off
- `ask` — a question; the answer becomes a checklist row

A bucket entry is `{"text": "AC adapter"}` or `{"type_id": N}` (one unit from
that accessory pool). An ask entry is `{"question": "Lens", "type_id": N}`
(choose from that pool) or `{"question": "...", "options": [...]}`.

An accessory picked as an answer brings its own `always` rules with it — that
is how a Canon lens pulls in a Metabones adapter.

An option can also be `{"label": "Wireless", "adds": [entries], "wireless": true}`:
choosing it puts `adds` on the checklist, and `wireless` stops the cable line
being drawn. An ask may carry `default` (an option label — it is then never
"open", has no empty choice, and is not remembered between projects) and
`when` (`live` / `not_live` — only asked in that mode). The camera preset's
**Connection** question uses all three: Cable (default) adds the HDMI cable,
Wireless adds a transmitter and a receiver, and it is only asked with live
mixing on. The rules editor keeps such entries intact but only creates the
simple kinds.

**Pools** hand out the owner's units first and rented ones only when those
run out. An empty pool produces a "short" row and a warning; there is no
automatic rental fallback.

**General rules** (`podplan_general_rules`) apply to any project with gear on
the plan — e.g. timecode whenever nothing is mixed live.

A kit with several pieces on the plan is still one section on the checklist:
its rules apply once per kit, not once per lav.

## Field of view

`Support/Optics` reads the focal length from the chosen lens's name (the wide
end of a zoom) and a speed booster's reduction from the adapter's name
(`0.64x`), and turns them into an angle for the Blackmagic 4K MFT sensor
(18.96 mm wide). `state.placements[].fov` carries it; the canvas draws the
cone to that angle. Whether cones are shown is a per-device preference
(`localStorage`), not project data.

Lights turn and throw a beam too (a standard 60 degrees, and a new light is
aimed at the table). `podplan_placements.fov` holds an angle set by hand for
a camera or a light; `null` means automatic.

## Camera numbers and the axis of action

Every camera gets a `number` when it is placed (`PlanService::addGear`, the
lowest number not in use on that plan). It is drawn on the camera body and
changed from the toolbar; `setNumber()` keeps numbers unique by swapping when
the number is taken. Numbers never reach the checklist. With a number on the
body, the wireless mark is drawn as a badge on the camera's top-right corner.

The axis of action (180° rule) is one line per project, stored as
`podplan_projects.axis` = `{on, x1, y1, x2, y2}` in canvas units. Turning it on
for the first time lays it through the first two people (`defaultAxis()`).
`PATCH projects/{project}/axis` shows, hides, moves or resets it. While it is
on, `PlanService::acrossTheAxis()` works out which side of the (infinite) line
each camera's centre is on: the side with more cameras is the working side, a
tie goes to the lowest-numbered camera, and the rest come back in the state
with `across: true` plus a `plan_warnings` entry. On screen the line is drawn
above the gear but grabbed from beneath it, so people and microphones sitting
on the line stay easy to pick up; its end handles are on top.

## Checklist views

Every section starts with a row for the gear itself (`self`, key
`p12:self`), so the camera is ticked as well as its tripod.

The Checklist is for packing only: nothing is chosen there. Besides All and
To pack it has a **Rented** filter (rows whose item has a provider). It sorts **by
gear** (one card per placed item) or **by kind** (one card per accessory type,
plus one for loose items), using the `kind` / `kind_sort` each generated row
carries. The sort and the All / To pack filter are per-device preferences.

Cards are laid out as masonry by `masonry()` in `partials/checklist-app`: as
many columns as fit at 290 px, and each card, in order, goes into the shortest
column so far. It re-runs after every render and when the width changes; while
the list is hidden (the shared page's Plan tab) it waits until it is shown.
Every redraw goes through `keepPlace()`, which holds the list's height and the
page's scroll position: without it the list is empty for a moment, the page
gets shorter, and a tick throws the reader back to the top.

## How the checklist is stored

It is not. `Support/ChecklistBuilder` recomputes every row from placements,
rules, the live switch and the answers. Only the packed state is kept
(`podplan_ticks`), keyed by a stable row key scoped to the placement
(`p12:t:ac-adapter`, `p12:a:<item>:<n>`). So a rebuild keeps its ticks, and
removing gear then adding it back starts unpacked (new placement, new keys).
Manual items live in `podplan_manual_items`.

## Cables and the mixer corner

Which gear is cabled comes from the gear type's `connection`: `hdmi` runs to
the nearest video mixer while live mixing is on, `xlr` to the nearest audio
mixer, `wireless` and `none` draw nothing. The server only says which pairs
are connected (`state.cables`); the canvas script draws the run.

A run never cuts across the set. It leaves its gear straight for the edge of
the canvas, follows the edge, and comes in to the mixer from there (`route()`
in `plan.blade.php`). Each cable has its own lane, so runs lie side by side.
Of the edges each end could use, the pair giving the shortest run wins, so it
works wherever a mixer is dragged.

`PlanService::MIXER_ZONE` is FOH, the bottom-right corner kept for the mixers:
they land there by default (`SLOTS` by role) and the Describe prompt tells
Claude to put them there. It is a default, not a cage — mixers still drag.

## Navigation

`partials/nav.blade.php` is on every screen: Projects, Plan, Checklist,
Inventory. Plan and Checklist open the current project, resolved by
`Support/CurrentProject`: the one on screen, else the one opened last
(session), else the next shoot coming up, else the most recently changed.

## The Describe step (Claude API)

`Support/PlanDescriber` sends the shoot settings, the request, the plan gear
(id, name, type, rented yes/no — never who it is rented from) and the current
plan, and asks for structured JSON: placements plus a list of things that are
not in the inventory. `PlanService::applyLayout()` then enforces it again —
unknown ids, accessories and duplicates are dropped, so gear the owner does
not have can never reach the plan. Gear that stays keeps its placement row,
so its lens and its ticks survive a regenerate. The previous layout is held
in `plan_undo` until the owner keeps or undoes the result.

- Model: `config('podplan.model')`, default `claude-opus-5-5` (`PODPLAN_MODEL`).
- Key: `ANTHROPIC_API_KEY`, shared with Receipts and My Music. Without it the
  Describe step is hidden and everything else works.
- Server-side refusal fallbacks are on (`fallbacks: "default"`).

## The secret link

`/podplan/s/{token}` — four routes that drop the `auth` middleware
(`routes.php`). The 40-character token is the only credential. It can read
one project's checklist and tick its rows, and look at its plan; it cannot add
items, answer open choices, reset, change the plan, or reach any other URL.
Replacing or stopping the share makes the old link a 404. Both the owner's
page and the shared page poll every 5 s, so everyone sees everyone's ticks.

The shared page has two tabs. **Plan** draws `s/{token}/plan`, which returns
`PlanService::publicState()`: the canvas, cables, axis, warnings and, per
placement, only what is drawn (`id, kind, symbol, role, label, sub, x, y, rot,
fov, number, wireless, wireless_rx, across`). No inventory, item ids, titles
or providers. It polls every 5 s while the tab is showing; `#plan` on the link
opens that tab first.

Both screens draw with `partials/plan-canvas.blade.php`:
`ppCanvas(svg, {plan, edit, view})`. With `edit: false` nothing carries
`data-id` or `data-axis`, so there is nothing to grab and a phone scrolls
freely over the drawing. Change how something is drawn there, once.

## Look

PodPlan wears the hub's own "Glass / Flow" look. `partials/styles.blade.php`
sets the sky palette on `body.ctx-podplan` and builds everything else from the
hub's tokens in `public/css/app.css` (panels, text, radii, shadows), so dark
mode follows the hub. The shared packing page loads the hub stylesheet too.

On a phone (760px and under) the Plan fits the whole diagram to the width,
draws labels and tap areas larger (`LS()` in `plan.blade.php`), and turns the
gear list, checklist preview and describe panel into bottom sheets opened from
a dock; `sheet()` sizes a sheet to the room left under the diagram so the
diagram is never covered.

## Versioning

PodPlan has its own version in `module.php` (`version`), shown beside its name
in the module menu (under the menu on a phone) and at the foot of the shared
packing page. Bump it with every change to the module, alongside the hub's
own `config/app.php` version and `CHANGELOG.md` entry.

## Files

- `Database/Migrations` — tables + built-in types, then the seeded inventory (only into an empty inventory).
- `Support/Rules.php` — rule buckets, normalising and comparing.
- `Support/ChecklistBuilder.php` — the rule engine.
- `Support/PlanService.php` — placements, default spots, layout snapshots, the Plan screen's state payload.
- `Support/ChecklistService.php` — payload + ticking shared by owner and guests.
- `Support/PlanDescriber.php` — the Claude call.
- `resources/views/partials/plan-canvas.blade.php` — the plan's drawing code (symbols, cables, axis), shared by the Plan screen and the secret-link page; `plan-legend.blade.php` is the legend under it.
- `resources/views/partials/styles.blade.php` — the module's styles in the hub's look, scoped to `body.ctx-podplan`.

Tests: `tests/Feature/PodPlanTest.php` (run against the seeded inventory).
