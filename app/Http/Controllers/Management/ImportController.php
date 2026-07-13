<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportCsvRequest;
use App\Models\Library;
use App\Support\Imports\BookImportService;
use App\Support\Imports\BranchImportService;
use App\Support\Imports\CsvFileReader;
use App\Support\Imports\LocationImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ImportController extends Controller
{
    public function show(Request $request, string $resource): View
    {
        $config = $this->config($request, $resource);

        return view('manage.imports.show', [
            'resource' => $resource,
            'config' => $config,
            'libraries' => $request->user()?->isSuperAdmin() && in_array($resource, ['branches', 'locations'], true)
                ? Library::query()->orderBy('name')->get(['id', 'name', 'code'])
                : collect(),
        ]);
    }

    public function store(
        ImportCsvRequest $request,
        string $resource,
        CsvFileReader $csvFileReader,
        BookImportService $bookImportService,
        BranchImportService $branchImportService,
        LocationImportService $locationImportService,
    ): RedirectResponse {
        $config = $this->config($request, $resource);

        try {
            $parsed = $csvFileReader->read($request->file('file'));
            $selectedLibraryId = $request->integer('library_id') ?: null;

            $summary = match ($resource) {
                'books' => $bookImportService->import($request->user(), $parsed['rows']),
                'branches' => $branchImportService->import($request->user(), $parsed['rows'], $selectedLibraryId),
                'locations' => $locationImportService->import($request->user(), $parsed['rows'], $selectedLibraryId),
                default => abort(404),
            };
        } catch (\Throwable $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route($config['index_route'])
            ->with('success', $this->successMessage($summary))
            ->with('import_report', [
                'resource' => $resource,
                'title' => $config['title'],
                'created' => $summary['created'] ?? 0,
                'updated' => $summary['updated'] ?? 0,
                'skipped' => $summary['skipped'] ?? 0,
                'failed' => $summary['failed'] ?? 0,
                'details' => $summary['details'] ?? [],
            ]);
    }

    public function template(Request $request, string $resource): Response
    {
        $config = $this->config($request, $resource);
        $handle = fopen('php://temp', 'w+');
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        fwrite($handle, "sep=;\r\n");
        fputcsv($handle, $config['headers'], ';');
        fputcsv($handle, $config['sample'], ';');
        rewind($handle);

        return response(stream_get_contents($handle) ?: '', 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $resource . '-template.csv"',
        ]);
    }

    private function config(Request $request, string $resource): array
    {
        $user = $request->user();

        return match ($resource) {
            'books' => tap([
                'title' => 'Knygų importas',
                'description' => 'Įkelkite CSV failą su knygomis. Keli autorių ir kategorijų slug skiriami simboliu |.',
                'headers' => [
                    'title',
                    'subtitle',
                    'isbn',
                    'publisher_name',
                    'publisher_country',
                    'author_slugs',
                    'category_slugs',
                    'publication_year',
                    'language',
                    'page_count',
                    'edition',
                    'description',
                    'cover_image',
                ],
                'sample' => [
                    '1984',
                    '',
                    '9786090142324',
                    'Alma littera',
                    'Lietuva',
                    'george-orwell',
                    'romanai|klasika',
                    '1949',
                    'lt',
                    '352',
                    '3',
                    'Distopinis romanas.',
                    '',
                ],
                'schema_fields' => [
                    'title',
                    'subtitle',
                    'isbn',
                    'publication_year',
                    'language',
                    'page_count',
                    'edition',
                    'description',
                    'cover_image',
                ],
                'relation_fields' => [
                    'publisher_name',
                    'publisher_country',
                    'author_slugs',
                    'category_slugs',
                ],
                'notes' => [
                    'publisher_name sukuria arba suranda įrašą publishers lenteleje.',
                    'author_slugs yra pagalbinis ryšio laukas. Jei autoriaus slug nerastas, autorius sukuriamas automatiškai.',
                    'category_slugs yra pagalbinis ryšio laukas. Jei kategorijos slug nerastas, kategorija sukuriama automatiškai.',
                ],
                'index_route' => 'books.index',
            ], fn () => abort_unless($user && ($user->isSuperAdmin() || $user->isAdmin() || $user->isStaff()), 403)),
            'branches' => tap([
                'title' => 'Filialų importas',
                'description' => 'Įkelkite CSV failą su filialais. Biblioteka paimama iš prisijungusio vartotojo arba pasirenkama formoje, jei importuoja superadmin.',
                'headers' => ['name', 'code', 'address', 'city'],
                'sample' => ['Senamiescio filialas', 'SEN-01', 'Pilies g. 10', 'Vilnius'],
                'schema_fields' => ['name', 'code', 'address', 'city'],
                'relation_fields' => [],
                'notes' => [
                    'library_id į failą nerašomas. Jis paimamas iš prisijungusio vartotojo arba superadmin pasirinktos bibliotekos.',
                ],
                'index_route' => 'manage.branches.index',
            ], fn () => abort_unless($user && ($user->isSuperAdmin() || $user->isAdmin()), 403)),
            'locations' => tap([
                'title' => 'Vietų importas',
                'description' => 'Įkelkite CSV failą su vietomis. Būtina nurodyti branch_code arba branch_name. Biblioteka paimama iš prisijungusio vartotojo arba pasirenkama formoje, jei importuoja superadmin.',
                'headers' => ['branch_code', 'branch_name', 'name', 'code', 'room', 'shelf', 'description'],
                'sample' => ['SEN-01', '', 'Grozines literaturos lentyna', 'V-001', '1', 'A-3', 'Prie lango'],
                'schema_fields' => ['name', 'code', 'room', 'shelf', 'description'],
                'relation_fields' => ['branch_code', 'branch_name'],
                'notes' => [
                    'branch_code arba branch_name naudojami tik filialui surasti pasirinktoje bibliotekoje.',
                    'library_id į failą nerašomas. Jis paimamas iš prisijungusio vartotojo arba superadmin pasirinktos bibliotekos.',
                ],
                'index_route' => 'manage.locations.index',
            ], fn () => abort_unless($user && ($user->isSuperAdmin() || $user->isAdmin() || $user->isStaff()), 403)),
            default => abort(404),
        };
    }

    /**
     * @param  array{created:int,updated:int,skipped:int,failed?:int,details?:array<int, array<string, string|int|null>>}  $summary
     */
    private function successMessage(array $summary): string
    {
        $parts = [
            sprintf('sukurta %d', $summary['created']),
        ];

        if (($summary['updated'] ?? 0) > 0) {
            $parts[] = sprintf('atnaujinta %d', $summary['updated']);
        }

        if (($summary['skipped'] ?? 0) > 0) {
            $parts[] = sprintf('praleista %d', $summary['skipped']);
        }

        if (($summary['failed'] ?? 0) > 0) {
            $parts[] = sprintf('klaidų %d', $summary['failed']);
        }

        return 'Importas baigtas: ' . implode(', ', $parts) . '.';
    }
}








