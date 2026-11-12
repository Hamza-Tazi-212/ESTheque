<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

class OpenLibrary
{
    public static function isbn(string $input): ?string
    {
        $isbn = strtoupper(preg_replace('/[\s-]+/', '', $input));
        if (preg_match('/^\d{9}[\dX]$/D', $isbn)) {
            $sum = 0;
            foreach (str_split($isbn) as $index => $digit) {
                $sum += (10 - $index) * ($digit === 'X' ? 10 : (int) $digit);
            }
            if ($sum % 11 !== 0) {
                return null;
            }
            $isbn = '978'.substr($isbn, 0, 9);
            return $isbn.self::checkDigit($isbn);
        }

        return preg_match('/^97[89]\d{10}$/D', $isbn) && self::checkDigit(substr($isbn, 0, 12)) === (int) $isbn[12]
            ? $isbn : null;
    }

    private static function checkDigit(string $prefix): int
    {
        $sum = 0;
        foreach (str_split($prefix) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }
        return (10 - $sum % 10) % 10;
    }

    public function search(string $field, string $query): array
    {
        $data = $this->get('/search.json', [$field => $query, 'limit' => 8, 'fields' => 'key,title,author_name']);
        return $this->rows($data, 'docs', 'W');
    }

    public function editions(string $work, int $page): array
    {
        $data = $this->get("/works/$work/editions.json", ['limit' => 12, 'offset' => ($page - 1) * 12]);
        return [$this->rows($data, 'entries', 'M'), (int) ($data['size'] ?? 0) > $page * 12];
    }

    private function rows(?array $data, string $field, string $suffix): array
    {
        if ($data === null) {
            return [];
        }
        if (! is_array($data[$field] ?? null)) {
            throw new RuntimeException('Réponse Open Library invalide.');
        }

        $results = [];
        foreach (array_slice($data[$field], 0, $suffix === 'W' ? 8 : 12) as $row) {
            $key = $this->key($row['key'] ?? null, $suffix);
            $title = $this->string($row['title'] ?? null);
            if ($key && $title) {
                $results[] = [
                    'key' => $key,
                    'title' => $title,
                    'detail' => implode(' · ', array_filter([
                        $this->list($row['author_name'] ?? null),
                        $this->string($row['publish_date'] ?? null),
                        $this->list($row['publishers'] ?? null),
                        $this->list($row['isbn_13'] ?? $row['isbn_10'] ?? null),
                    ])),
                ];
            }
        }

        return $results;
    }

    public function edition(string $id, ?string $isbn = null): ?array
    {
        $edition = $this->get($isbn ? "/isbn/$id.json" : "/books/$id.json");
        if ($edition === null) {
            return null;
        }
        $key = $this->key($edition['key'] ?? null, 'M');
        $title = $this->string($edition['title'] ?? null);
        $isbns = [];
        foreach (['isbn_13', 'isbn_10'] as $field) {
            foreach (is_array($edition[$field] ?? null) ? $edition[$field] : [] as $value) {
                if (is_string($value) && ($canonical = self::isbn($value))) {
                    $isbns[$canonical] = true;
                }
            }
        }
        if (! $key || ! $title || (! $isbn && $key !== $id) || ($isbn && ! isset($isbns[$isbn]))) {
            throw new RuntimeException('Impossible de confirmer cette édition auprès d’Open Library.');
        }

        $workKey = $this->key($edition['works'][0]['key'] ?? null, 'W');
        $work = $workKey ? $this->related("/works/$workKey.json") : [];
        $authors = [];
        $editionAuthors = is_array($edition['authors'] ?? null) ? $edition['authors'] : [];
        $authorEntries = $editionAuthors ?: (is_array($work['authors'] ?? null) ? $work['authors'] : []);
        foreach (array_slice($authorEntries, 0, 5) as $entry) {
            $authorKey = $this->key($entry['key'] ?? $entry['author']['key'] ?? null, 'A');
            if ($authorKey && ($name = $this->string($this->related("/authors/$authorKey.json")['name'] ?? null))) {
                $authors[] = $name;
            }
        }
        $languages = [];
        foreach (is_array($edition['languages'] ?? null) ? $edition['languages'] : [] as $language) {
            if (is_string($language['key'] ?? null) && preg_match('~^/languages/([a-z]{3})$~D', $language['key'], $match)) {
                $languages[] = $match[1];
            }
        }
        $cover = null;
        foreach (is_array($edition['covers'] ?? null) ? $edition['covers'] : [] as $candidate) {
            if (is_int($candidate) && $candidate > 0) {
                $cover = $candidate;
                break;
            }
        }
        $description = $edition['description'] ?? $work['description'] ?? null;
        $subjects = $edition['subjects'] ?? $work['subjects'] ?? null;

        return [
            'title' => $title,
            'subtitle' => $this->string($edition['subtitle'] ?? null),
            'author' => implode(', ', $authors),
            'isbn' => $isbn ?? ($isbns ? (string) array_key_first($isbns) : null),
            'publisher' => $this->list($edition['publishers'] ?? null),
            'publication_date' => $this->string($edition['publish_date'] ?? null),
            'edition_statement' => $this->string($edition['edition_name'] ?? null),
            'language' => implode(', ', array_unique($languages)),
            'summary_from_work' => ! isset($edition['description']) && isset($work['description']),
            'subjects_from_work' => ! isset($edition['subjects']) && isset($work['subjects']),
            'summary' => $this->string(is_array($description) ? ($description['value'] ?? null) : $description),
            'subjects' => $this->list($subjects),
            'open_library_edition' => $key,
            'open_library_work' => $workKey,
            'open_library_cover' => $cover,
        ];
    }

    private function related(string $path): array
    {
        try {
            return $this->get($path) ?? [];
        } catch (RuntimeException) {
            return [];
        }
    }

    private function key(mixed $value, string $suffix): ?string
    {
        return is_string($value) && preg_match('~^(?:/(?:books|works|authors)/)?(OL\d+'.$suffix.')$~D', $value, $match)
            ? $match[1] : null;
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function list(mixed $value): ?string
    {
        return is_array($value) ? $this->string(implode(', ', array_filter($value, 'is_string'))) : null;
    }

    private function get(string $path, array $query = []): ?array
    {
        $key = 'open-library:'.hash('sha256', $path.'?'.http_build_query($query));
        if (($cached = Cache::get($key)) !== null) {
            return $cached === false ? null : $cached;
        }

        $contact = config('services.open_library.contact');
        $agent = filter_var($contact, FILTER_VALIDATE_EMAIL) ? 'ESTheque ('.$contact.')' : 'ESTheque';

        try {
            return Cache::lock('open-library:request', 15)->block(6, function () use ($key, $agent, $path, $query) {
                if (($cached = Cache::get($key)) !== null) {
                    return $cached === false ? null : $cached;
                }

                if (Cache::get('open-library:cooldown')) {
                    throw new RuntimeException('Open Library limite temporairement les recherches. Réessayez plus tard ou utilisez la saisie manuelle.');
                }

                // Stay within the anonymous rate limit even when a contact is configured.
                if (RateLimiter::tooManyAttempts('open-library:outbound', 1)) {
                    usleep(1000000);
                }
                RateLimiter::hit('open-library:outbound', 1);

                $response = Http::acceptJson()->withUserAgent($agent)->connectTimeout(2)->timeout(4)
                    ->withOptions(['allow_redirects' => ['max' => 3, 'protocols' => ['https'], 'on_redirect' =>
                        static function ($request, $response, $uri) {
                            if ($uri->getHost() !== 'openlibrary.org' || ! preg_match('~^/(?:books/OL[0-9]+M|isbn/[0-9X]+)\.json$~D', $uri->getPath())) {
                                throw new RuntimeException('Redirection Open Library invalide.');
                            }
                        }]])
                    ->get('https://openlibrary.org'.$path, $query);

                if ($response->status() === 429) {
                    $retryAfter = $response->header('Retry-After');
                    $delay = ctype_digit($retryAfter) ? (int) $retryAfter : (strtotime($retryAfter) ?: 0) - time();
                    Cache::put('open-library:cooldown', true, max(60, $delay));
                    throw new RuntimeException('Open Library limite temporairement les recherches. Réessayez plus tard ou utilisez la saisie manuelle.');
                }
                if ($response->notFound()) {
                    Cache::put($key, false, now()->addMinutes(15));

                    return null;
                }
                $data = $response->json();
                if (! $response->ok() || ! is_array($data) || array_is_list($data)) {
                    throw new RuntimeException('Open Library est momentanément indisponible ou a retourné une réponse invalide.');
                }
                Cache::put($key, $data, now()->addDay());

                return $data;
            });
        } catch (\Illuminate\Http\Client\ConnectionException|\Illuminate\Contracts\Cache\LockTimeoutException $exception) {
            throw new RuntimeException('Open Library ne répond pas. Réessayez ou saisissez la fiche manuellement.', previous: $exception);
        }
    }
}
