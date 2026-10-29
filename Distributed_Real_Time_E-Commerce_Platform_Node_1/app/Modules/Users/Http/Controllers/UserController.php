<?php

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Users\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    /**
     * Admin-only listing of all accounts (route is guarded by role:Responsible).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'role' => ['nullable', 'in:Customer,Responsible'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::query()
            ->when($validated['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return UserResource::collection($users);
    }
}
