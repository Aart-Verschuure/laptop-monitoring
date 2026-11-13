<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Importeert Chrome-geschiedenis uit Google Takeout (takeout.google.com → Chrome).
 * Omdat dit de geschiedenis van je Google-account is, staan hier ook bezoeken
 * van andere apparaten in waarop je met hetzelfde account bent ingelogd en
 * synchronisatie aan staat, zoals Chrome op je telefoon.
 */
class BrowsingImportController extends Controller
{
    public function __invoke(Request $request, Device $device): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:204800'],
        ], [
            'file.uploaded' => 'Uploaden mislukt. Is het bestand groter dan upload_max_filesize in php.ini?',
        ]);

        $json = json_decode(file_get_contents($request->file('file')->getRealPath()), true);
        $entries = is_array($json) ? ($json['Browser History'] ?? (array_is_list($json) ? $json : null)) : null;

        if (! is_array($entries)) {
            return back()->withErrors(['file' => 'Dit is geen Chrome-geschiedenis uit Google Takeout (BrowserHistory.json of History.json).']);
        }

        $now = now();
        $rows = [];
        $total = 0;
        $imported = 0;

        foreach ($entries as $entry) {
            $url = $entry['url'] ?? null;
            $usec = $entry['time_usec'] ?? null;

            if (! is_string($url) || ! preg_match('#^https?://#i', $url) || strlen($url) > 4000 || ! is_numeric($usec)) {
                continue;
            }

            $total++;
            $rows[] = [
                'device_id' => $device->id,
                'url' => $url,
                'domain' => parse_url($url, PHP_URL_HOST) ?: null,
                'title' => isset($entry['title']) && $entry['title'] !== '' ? Str::limit((string) $entry['title'], 250) : null,
                'browser' => 'Chrome (Google-account)',
                'incognito' => false,
                'source_id' => 'takeout:'.sha1($url.'|'.$usec),
                'visited_at' => Carbon::createFromTimestampMs(intdiv((int) $usec, 1000))->setTimezone(config('app.timezone')),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) === 500) {
                $imported += $device->browsingVisits()->getQuery()->insertOrIgnore($rows);
                $rows = [];
            }
        }

        if ($rows) {
            $imported += $device->browsingVisits()->getQuery()->insertOrIgnore($rows);
        }

        return back()->with('status', "Google-geschiedenis geïmporteerd: {$imported} nieuwe bezoeken".
            ($total > $imported ? ', '.($total - $imported).' stonden er al in.' : '.'));
    }
}
