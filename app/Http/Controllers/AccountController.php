<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(private readonly AccountService $accountService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Account::query()->orderBy('sort_order')->orderBy('code');

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 100)));
    }

    public function store(StoreAccountRequest $request): JsonResponse
    {
        $account = $this->accountService->create($request->validated());

        return response()->json([
            'message' => 'Akun berhasil ditambahkan.',
            'data' => $account,
        ], 201);
    }

    public function show(Account $account): JsonResponse
    {
        return response()->json(['data' => $account->load('parent', 'children')]);
    }

    public function update(UpdateAccountRequest $request, Account $account): JsonResponse
    {
        $account = $this->accountService->update($account, $request->validated());

        return response()->json([
            'message' => 'Akun berhasil diperbarui.',
            'data' => $account,
        ]);
    }

    public function destroy(Account $account): JsonResponse
    {
        $this->accountService->delete($account);

        return response()->json(['message' => 'Akun berhasil dihapus.']);
    }
}
