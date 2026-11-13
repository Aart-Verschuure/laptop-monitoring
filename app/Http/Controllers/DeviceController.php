<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function home(): RedirectResponse
    {
        $device = Device::orderByRaw("type = 'laptop' desc")->orderBy('id')->first();

        return $device
            ? redirect()->route('devices.dashboard', $device)
            : redirect()->route('devices.index');
    }

    public function index(): View
    {
        return view('devices.index', [
            'devices' => Device::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(Device::TYPES))],
        ], [
            'name.required' => 'Geef het apparaat een naam.',
        ]);

        [$device, $token] = Device::createWithToken($data['name'], $data['type']);

        return redirect()->route('devices.index')
            ->with('new_token', ['device' => $device->name, 'token' => $token]);
    }

    public function rotateToken(Device $device): RedirectResponse
    {
        $token = Str::random(48);
        $device->update(['token_hash' => hash('sha256', $token)]);

        return redirect()->route('devices.index')
            ->with('new_token', ['device' => $device->name, 'token' => $token]);
    }

    public function destroy(Device $device): RedirectResponse
    {
        $device->delete();

        return redirect()->route('devices.index')->with('status', "Apparaat \"{$device->name}\" is verwijderd.");
    }
}
