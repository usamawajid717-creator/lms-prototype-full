<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogImportController extends Controller
{
    private const MAX_ROWS = 500;

    private const MAX_COPIES_PER_IMPORT = 10000;

    public function create(): View
    {
        return view('catalog.import');
    }

    public function template()
    {
        return response(
            "isbn,title,authors,publisher,publication_year,edition,category,total_copies\r\n",
            200,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="catalog-import-template.csv"',
            ],
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return back()->withErrors(['file' => 'The uploaded CSV could not be read.']);
        }

        $errors = [];
        $rows = [];
        $totalCopies = 0;
        $dataRows = 0;
        $seenIsbns = [];
        $categories = BookCategory::query()->where('is_active', true)->get()->keyBy(
            fn (BookCategory $category): string => mb_strtolower($category->name),
        );

        try {
            $headers = fgetcsv($handle, 0, ',', '"', '\\');
            if (! is_array($headers)) {
                return back()->withErrors(['file' => 'The CSV file is empty.']);
            }

            $headers = array_map(fn ($header): string => trim((string) $header), $headers);
            if (isset($headers[0])) {
                $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
            }

            $requiredHeaders = ['isbn', 'title', 'authors', 'category', 'total_copies'];
            if (count($headers) !== count(array_unique($headers)) || array_diff($requiredHeaders, $headers)) {
                return back()->withErrors([
                    'file' => 'CSV headers must include unique columns: isbn, title, authors, category, total_copies. Publisher, publication_year, and edition are optional.',
                ]);
            }

            $line = 1;
            while (($values = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                $line++;
                if ($values === [null] || count(array_filter($values, fn ($value): bool => trim((string) $value) !== '')) === 0) {
                    continue;
                }
                $dataRows++;
                if ($dataRows > self::MAX_ROWS) {
                    $errors[] = 'The CSV may contain no more than '.self::MAX_ROWS.' book rows.';
                    break;
                }
                if (count($values) !== count($headers)) {
                    $errors[] = "Row {$line}: the number of columns does not match the header.";
                    continue;
                }

                $input = array_combine($headers, array_map(fn ($value): string => trim((string) $value), $values));
                $validator = Validator::make($input, [
                    'isbn' => ['required', 'string', 'max:20', Rule::unique('books', 'isbn')],
                    'title' => ['required', 'string', 'max:255'],
                    'authors' => ['required', 'string', 'max:255'],
                    'publisher' => ['nullable', 'string', 'max:150'],
                    'publication_year' => ['nullable', 'integer', 'between:1000,'.(now()->year + 1)],
                    'edition' => ['nullable', 'string', 'max:100'],
                    'category' => ['required', 'string', 'max:100'],
                    'total_copies' => ['required', 'integer', 'min:0', 'max:10000'],
                ]);

                if ($validator->fails()) {
                    foreach ($validator->errors()->all() as $error) {
                        $errors[] = "Row {$line}: {$error}";
                    }
                    continue;
                }

                $data = $validator->validated();
                $isbnKey = mb_strtolower($data['isbn']);
                if (isset($seenIsbns[$isbnKey])) {
                    $errors[] = "Row {$line}: ISBN duplicates row {$seenIsbns[$isbnKey]} in this file.";
                    continue;
                }
                $seenIsbns[$isbnKey] = $line;

                $category = $categories->get(mb_strtolower($data['category']));
                if (! $category) {
                    $errors[] = "Row {$line}: category \"{$data['category']}\" is not an active category.";
                    continue;
                }

                $data['category_id'] = $category->id;
                unset($data['category']);
                $totalCopies += (int) $data['total_copies'];
                if ($totalCopies > self::MAX_COPIES_PER_IMPORT) {
                    $errors[] = 'The total number of copies in one import may not exceed '.self::MAX_COPIES_PER_IMPORT.'.';
                    break;
                }
                $rows[] = $data;
            }
        } finally {
            fclose($handle);
        }

        if ($errors) {
            return back()->withErrors(['file' => array_slice($errors, 0, 50)]);
        }
        if (! $rows) {
            return back()->withErrors(['file' => 'The CSV contains no book rows to import.']);
        }

        DB::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                $row['available_copies'] = $row['total_copies'];
                Book::create($row);
            }
        });

        return redirect()->route('catalog.admin.index')->with('status', count($rows).' books imported successfully.');
    }
}
