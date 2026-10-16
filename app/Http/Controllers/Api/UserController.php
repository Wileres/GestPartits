<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        return UserResource::collection(User::query()->with('role')->orderBy('username')->get());
    }

    public function store(StoreUserRequest $request): UserResource
    {
        return new UserResource($this->users->create($request->validated()));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        return new UserResource($this->users->update($request->user(), $user, $request->validated()));
    }

    public function destroy(Request $request, User $user): Response
    {
        $this->authorize('delete', $user);

        $this->users->delete($request->user(), $user);

        return response()->noContent();
    }
}
