<?php

namespace App\Http\Controllers\Management;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManageCategoryRequest;
use App\Models\Category;
use App\Queries\Management\AuditLogs\GetRecentAuditLogsForCategoryQuery;
use App\Queries\Management\Categories\GenerateUniqueCategorySlugQuery;
use App\Queries\Management\Categories\GetManageCategoriesQuery;
use App\Support\AuditLogChanges;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request, GetManageCategoriesQuery $getManageCategoriesQuery): View
    {
        return view('manage.categories.index', [
            'categories' => $getManageCategoriesQuery->handle(trim((string) $request->query('search', ''))),
        ]);
    }

    public function create(): View
    {
        return view('manage.categories.create', [
            'category' => new Category(),
        ]);
    }

    public function store(
        ManageCategoryRequest $request,
        GenerateUniqueCategorySlugQuery $generateUniqueCategorySlugQuery
    ): RedirectResponse {
        $category = Category::create($this->payload($request, $generateUniqueCategorySlugQuery));

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'category_created',
            $category,
            sprintf('Sukurta kategorija "%s".', $category->name),
            ['category_name' => $category->name]
        );

        return redirect()
            ->route('manage.categories.index')
            ->with('success', 'Kategorija sukurta.');
    }

    public function edit(Request $request, Category $category, GetRecentAuditLogsForCategoryQuery $getRecentAuditLogsForCategoryQuery): View
    {
        return view('manage.categories.edit', [
            'category' => $category,
            'auditLogs' => $request->user()?->isSuperAdmin()
                ? $getRecentAuditLogsForCategoryQuery->handle($category)
                : collect(),
        ]);
    }

    public function update(
        ManageCategoryRequest $request,
        Category $category,
        GenerateUniqueCategorySlugQuery $generateUniqueCategorySlugQuery
    ): RedirectResponse {
        $category->fill($this->payload($request, $generateUniqueCategorySlugQuery));
        $changedFields = array_keys($category->getDirty());
        $changeSummary = AuditLogChanges::fromModel($category, $changedFields);
        $category->save();

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'category_updated',
            $category,
            sprintf('Atnaujinta kategorija "%s".', $category->name),
            array_merge([
                'category_name' => $category->name,
            ], $changeSummary)
        );

        return redirect()
            ->route('manage.categories.index')
            ->with('success', 'Kategorija atnaujinta.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->books()->exists() || $category->primaryBooks()->exists()) {
            return back()->with('error', 'Kategorijos ištrinti negalima, nes ji naudojama knygose.');
        }

        app(RecordAuditLogAction::class)->handle(
            $request->user(),
            'category_deleted',
            $category,
            sprintf('Ištrinta kategorija "%s".', $category->name),
            [
                'category_name' => $category->name,
                'snapshot' => [
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                ],
            ]
        );

        $category->delete();

        return redirect()
            ->route('manage.categories.index')
            ->with('success', 'Kategorija ištrinta.');
    }

    private function payload(
        ManageCategoryRequest $request,
        GenerateUniqueCategorySlugQuery $generateUniqueCategorySlugQuery
    ): array {
        $validated = $request->validated();
        $name = $validated['name'];
        $slug = $validated['slug'] ?: Str::slug($name);

        return [
            'name' => $name,
            'slug' => $generateUniqueCategorySlugQuery->handle($slug, $request->route('category')?->id),
            'description' => $validated['description'] ?? null,
        ];
    }
}








