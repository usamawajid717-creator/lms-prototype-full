<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\InventoryAudit;
use App\Models\InventoryAuditItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryAuditController extends Controller
{
    public function index(): View
    {
        $audits = InventoryAudit::query()
            ->with('starter')
            ->withCount([
                'items as expected_count' => fn ($query) => $query->where('expected', true),
                'items as scanned_count' => fn ($query) => $query->where('expected', true)->whereNotNull('scanned_at'),
                'items as unexpected_count' => fn ($query) => $query->where('expected', false),
            ])
            ->latest('started_at')
            ->paginate(15);

        return view('inventory-audits.index', compact('audits'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);

        $audit = DB::transaction(function () use ($request, $validated): InventoryAudit {
            if (InventoryAudit::query()->where('status', 'open')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'audit' => 'Complete the currently open inventory audit before starting another.',
                ]);
            }

            $audit = InventoryAudit::create([
                'name' => trim($validated['name']),
                'status' => 'open',
                'started_by' => $request->user()->id,
                'started_at' => now(),
            ]);

            BookCopy::query()
                ->whereIn('status', ['available', 'unavailable'])
                ->with('book:id,title')
                ->orderBy('id')
                ->chunkById(500, function ($copies) use ($audit): void {
                    $now = now();
                    $items = $copies->map(fn (BookCopy $copy): array => [
                        'inventory_audit_id' => $audit->id,
                        'book_copy_id' => $copy->id,
                        'barcode' => $copy->barcode,
                        'book_title' => $copy->book->title,
                        'copy_number' => $copy->copy_number,
                        'expected_status' => $copy->status,
                        'expected' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all();

                    InventoryAuditItem::query()->insert($items);
                });

            return $audit;
        });

        return redirect()->route('inventory-audits.show', $audit)
            ->with('status', 'Inventory audit started. Scan the expected shelf copies.');
    }

    public function show(InventoryAudit $inventoryAudit): View
    {
        $items = $inventoryAudit->items()
            ->with('copy')
            ->orderByRaw('expected DESC')
            ->orderBy('barcode')
            ->paginate(40);

        return view('inventory-audits.show', [
            'audit' => $inventoryAudit->load('starter', 'completer'),
            'items' => $items,
            'expectedCount' => $inventoryAudit->items()->where('expected', true)->count(),
            'scannedCount' => $inventoryAudit->items()->where('expected', true)->whereNotNull('scanned_at')->count(),
            'missingCount' => $inventoryAudit->items()->where('expected', true)->whereNull('scanned_at')->count(),
            'unexpectedCount' => $inventoryAudit->items()->where('expected', false)->count(),
        ]);
    }

    public function scan(Request $request, InventoryAudit $inventoryAudit): RedirectResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:40'],
        ]);
        $barcode = trim($validated['barcode']);

        $result = DB::transaction(function () use ($request, $inventoryAudit, $barcode): string {
            $audit = InventoryAudit::query()->lockForUpdate()->findOrFail($inventoryAudit->id);
            if ($audit->status !== 'open') {
                throw ValidationException::withMessages(['audit' => 'This audit is complete and no longer accepts scans.']);
            }

            $item = InventoryAuditItem::query()
                ->where('inventory_audit_id', $audit->id)
                ->where('barcode', $barcode)
                ->lockForUpdate()
                ->first();

            if ($item) {
                if ($item->scanned_at) {
                    throw ValidationException::withMessages(['barcode' => 'This barcode has already been scanned in this audit.']);
                }

                $item->update([
                    'scanned_at' => now(),
                    'scanned_by' => $request->user()->id,
                ]);

                return $item->expected ? 'found' : 'unexpected-known';
            }

            $copy = BookCopy::query()->with('book:id,title')->where('barcode', $barcode)->first();
            InventoryAuditItem::create([
                'inventory_audit_id' => $audit->id,
                'book_copy_id' => $copy?->id,
                'barcode' => $barcode,
                'book_title' => $copy?->book->title,
                'copy_number' => $copy?->copy_number,
                'expected_status' => $copy?->status,
                'expected' => false,
                'scanned_at' => now(),
                'scanned_by' => $request->user()->id,
            ]);

            return $copy ? 'unexpected-known' : 'unexpected-unknown';
        });

        $message = match ($result) {
            'found' => "Copy {$barcode} scanned.",
            'unexpected-known' => "Copy {$barcode} was scanned but was not expected on the shelf.",
            'unexpected-unknown' => "Barcode {$barcode} is not registered in the catalog and was recorded as unexpected.",
        };

        return redirect()->route('inventory-audits.show', $inventoryAudit)->with('status', $message);
    }

    public function complete(Request $request, InventoryAudit $inventoryAudit): RedirectResponse
    {
        DB::transaction(function () use ($request, $inventoryAudit): void {
            $audit = InventoryAudit::query()->lockForUpdate()->findOrFail($inventoryAudit->id);
            if ($audit->status !== 'open') {
                throw ValidationException::withMessages(['audit' => 'This inventory audit is already complete.']);
            }

            $audit->update([
                'status' => 'completed',
                'completed_by' => $request->user()->id,
                'completed_at' => now(),
            ]);
        });

        return redirect()->route('inventory-audits.show', $inventoryAudit)->with('status', 'Audit completed. Review missing and unexpected copies below.');
    }

    public function markLost(Request $request, InventoryAudit $inventoryAudit, InventoryAuditItem $item): RedirectResponse
    {
        abort_unless($item->inventory_audit_id === $inventoryAudit->id, 404);

        DB::transaction(function () use ($request, $inventoryAudit, $item): void {
            $audit = InventoryAudit::query()->lockForUpdate()->findOrFail($inventoryAudit->id);
            $auditItem = InventoryAuditItem::query()->lockForUpdate()->findOrFail($item->id);
            if ($audit->status !== 'completed' || ! $auditItem->expected || $auditItem->scanned_at || ! $auditItem->book_copy_id) {
                throw ValidationException::withMessages([
                    'copy' => 'Only an unscanned expected copy from a completed audit can be reconciled as lost.',
                ]);
            }

            $copyRecord = BookCopy::query()->findOrFail($auditItem->book_copy_id);
            $book = Book::query()->lockForUpdate()->findOrFail($copyRecord->book_id);
            $copy = BookCopy::query()->lockForUpdate()->findOrFail($copyRecord->id);
            if (! in_array($copy->status, ['available', 'unavailable'], true)) {
                throw ValidationException::withMessages([
                    'copy' => 'This copy is now on loan, reserved, or otherwise unavailable for loss reconciliation. Review its current status first.',
                ]);
            }

            if ($copy->status === 'available') {
                $book->decrement('available_copies');
            }
            $copy->update(['status' => 'lost']);
            $auditItem->update([
                'reconciled_at' => now(),
                'reconciled_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('inventory-audits.show', $inventoryAudit)->with('status', 'Missing copy marked lost.');
    }
}
