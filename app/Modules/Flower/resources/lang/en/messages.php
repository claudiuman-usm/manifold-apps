<?php

return [
    'title' => 'Flow-er',
    'tagline' => 'Timed editing checklists',

    'nav' => [
        'templates' => 'Templates',
    ],

    'index' => [
        'heading' => 'Templates',
        'subheading' => 'Your checklists, grouped by client and type.',
        'new_template' => 'New template',
        'empty' => 'No templates yet. Create your first checklist.',
        'no_steps' => 'No steps yet',
        'steps_count' => '{0}No steps|{1}:count step|[2,*]:count steps',
        'runs_count' => '{0}No runs|{1}:count run|[2,*]:count runs',
        'start_run' => 'Start run',
        'edit' => 'Edit',
        'history' => 'History',
    ],

    'template' => [
        'create_heading' => 'New template',
        'edit_heading' => 'Edit template',
        'name' => 'Template name',
        'name_placeholder' => 'e.g. Main edit',
        'client' => 'Client',
        'client_placeholder' => 'e.g. AcmeCorp',
        'type' => 'Type',
        'type_placeholder' => 'e.g. Podcast, Reels',
        'save' => 'Save template',
        'create' => 'Create template',
        'delete' => 'Delete template',
        'delete_confirm' => 'Delete this template? Its runs and history will be hidden.',
        'created' => 'Template created. Now add its steps.',
        'updated' => 'Template saved.',
        'deleted' => 'Template deleted.',
        'back' => 'Back to templates',
    ],

    'steps' => [
        'heading' => 'Steps',
        'hint' => 'Add steps in order. Drag to reorder.',
        'name_placeholder' => 'Step name',
        'add' => 'Add step',
        'remove' => 'Remove',
        'empty' => 'No steps yet — add the first one below.',
    ],

    'run' => [
        'heading' => 'Run',
        'run_number' => 'Run #:number',
        'start' => 'Start run',
        'no_steps' => 'This template has no steps. Add steps before starting a run.',
        'current_step' => 'Current step',
        'elapsed' => 'Elapsed',
        'average' => 'Average',
        'check' => 'Done — next step',
        'check_last' => 'Done — finish run',
        'progress' => ':done of :total steps',
        'pending' => 'Pending',
        'done' => 'Done',
        'no_history' => 'no history',
        'cancel' => 'Cancel run',
        'cancel_confirm' => 'Cancel this run? It will not be saved to history.',
        'cancelled' => 'Run cancelled.',
    ],

    'nudge' => [
        'title' => 'Keep it flowing 🌊',
        'body' => "You're over your usual pace for this step.",
    ],

    'summary' => [
        'heading' => 'Run complete',
        'subheading' => 'How this run compared to your average.',
        'total' => 'Total time',
        'step' => 'Step',
        'duration' => 'This run',
        'average' => 'Average',
        'delta' => 'Difference',
        'faster' => 'faster',
        'slower' => 'slower',
        'start_over' => 'Start over',
        'view_history' => 'View history',
        'back' => 'Back to templates',
    ],

    'history' => [
        'heading' => 'Run history',
        'subheading' => 'Completed runs of :template.',
        'empty' => 'No completed runs yet.',
        'run' => 'Run',
        'date' => 'Date',
        'total' => 'Total',
        'average_row' => 'Average',
        'back' => 'Back to templates',
        'start_run' => 'Start run',
    ],

    'unit' => [
        'seconds_short' => 's',
        'minutes_short' => 'm',
    ],
];
