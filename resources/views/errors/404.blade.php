@include('errors.page', [
    'code' => '404',
    'title' => 'Puslapis nerastas',
    'description' => 'Atsiprašome, bet šis puslapis neegzistuoja arba buvo perkeltas.',
    'icon' => 'document-x',
    'primaryLabel' => 'Grįžti į pradžią',
    'primaryUrl' => route('home'),
    'secondaryLabel' => 'Atgal',
    'secondaryAction' => 'back',
])







