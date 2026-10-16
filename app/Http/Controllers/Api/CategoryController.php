<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\ApplicationSetting;
use App\Models\Category;
use App\Support\UniqueSlug;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Category::class);

        return CategoryResource::collection(Category::query()->withCount('teams')->orderBy('sort_order')->orderBy('name')->get());
    }

    public function store(CategoryRequest $request): CategoryResource
    {
        $category = Category::query()->create([
            ...$request->validated(),
            'slug' => UniqueSlug::for(Category::class, $request->string('name')->value()),
        ]);

        return new CategoryResource($category->loadCount('teams'));
    }

    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        $category->update($request->validated());

        return new CategoryResource($category->loadCount('teams'));
    }

    public function destroy(Category $category): Response
    {
        $this->authorize('delete', $category);

        if ($category->teams()->exists()) {
            throw ValidationException::withMessages(['category' => __('messages.categories.has_teams')]);
        }

        $category->delete();

        return response()->noContent();
    }

    /**
     * L'administrador confirma que ha revisat les durades de demostració.
     */
    public function markDurationsReviewed(Request $request): Response
    {
        $this->authorize('manage-settings');

        ApplicationSetting::put('demo_durations_pending_review', false, $request->user()->id);

        return response()->noContent();
    }
}
