<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJournalEntryRequest;
use App\Http\Requests\UpdateJournalEntryRequest;
use App\Models\JournalEntry;
use App\Services\JournalEntryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function __construct(private readonly JournalEntryService $journalEntryService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = JournalEntry::query()->with('lines.account')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(StoreJournalEntryRequest $request): JsonResponse
    {
        $entry = $this->journalEntryService->createDraft(
            $request->validated(),
            (int) $request->user()->id
        );

        return response()->json([
            'message' => 'Jurnal draf berhasil dibuat.',
            'data' => $entry,
        ], 201);
    }

    public function show(JournalEntry $journalEntry): JsonResponse
    {
        return response()->json([
            'data' => $journalEntry->load('lines.account', 'creator:id,name'),
        ]);
    }

    public function update(UpdateJournalEntryRequest $request, JournalEntry $journalEntry): JsonResponse
    {
        $entry = $this->journalEntryService->updateDraft($journalEntry, $request->validated());

        return response()->json([
            'message' => 'Jurnal draf berhasil diperbarui.',
            'data' => $entry,
        ]);
    }

    public function destroy(JournalEntry $journalEntry): JsonResponse
    {
        $this->journalEntryService->deleteDraft($journalEntry);

        return response()->json(['message' => 'Jurnal draf berhasil dihapus.']);
    }

    public function post(JournalEntry $journalEntry): JsonResponse
    {
        $entry = $this->journalEntryService->post($journalEntry);

        return response()->json([
            'message' => 'Jurnal berhasil diposting.',
            'data' => $entry,
        ]);
    }
}
