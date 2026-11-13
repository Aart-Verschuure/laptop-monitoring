<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Importeert Chrome-geschiedenis uit Google Takeout (takeout.google.com → Chrome).
 * Omdat dit de geschiedenis van je Google-account is, staan hier ook bezoeken
 * van andere apparaten in waarop je met hetzelfde account bent ingelogd en
 * synchronisatie aan staat, zoals Chrome op je telefoon.
 *
 * Takeout-bestanden zijn vaak tientallen MB's. De browser leest het bestand
 * daarom zelf en stuurt de bezoeken in stukjes; zo gelden de upload- en
 * geheugenlimieten van PHP niet.
 */
class BrowsingImportController extends Controller
{
    public const CHUNK = 1000;

    public function __invoke(Request $request, Device $device): JsonResponse
    {
        $data = $request->validate([
            'entries' => ['present', 'array', 'max:'.self::CHUNK],
        ]);

        $now = now();
        $rows = [];

        foreach ($data['entries'] as $entry) {
            $url = $entry['url'] ?? null;
            $usec = $entry['time_usec'] ?? null;

            if (! is_string($url) || ! preg_match('#^https?://#i', $url) || strlen($url) > 4000 || ! is_numeric($usec)) {
                continue;
            }

            $title = $entry['title'] ?? null;

            $rows[] = [
                'device_id' => $device->id,
                'url' => $url,
                'domain' => parse_url($url, PHP_URL_HOST) ?: null,
                'title' => is_string($title) && $title !== '' ? Str::limit($title, 250) : null,
                'browser' => 'Chrome (Google-account)',
                'incognito' => false,
                'source_id' => 'takeout:'.sha1($url.'|'.$usec),
                'visited_at' => Carbon::createFromTimestampMs(intdiv((int) $usec, 1000))->setTimezone(config('app.timezone')),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $imported = 0;
        foreach (array_chunk($rows, 250) as $chunk) {
            $imported += $device->browsingVisits()->getQuery()->insertOrIgnore($chunk);
        }

        return response()->json([
            'imported' => $imported,
            'skipped' => count($data['entries']) - $imported,
        ]);
    }
}
