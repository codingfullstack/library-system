@include('errors.page', [
    'code' => '500',
    'title' => 'Įvyko klaida',
    'description' => 'Sistema susidūrė su netikėta klaida. Pabandykite dar kartą vėliau.',
    'icon' => 'warning',
    'primaryLabel' => 'Bandyti dar kartą',
    'primaryAction' => 'reload',
    'secondaryLabel' => 'Į pradžią',
    'secondaryUrl' => route('home'),
])







