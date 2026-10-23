<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureNotInstalled;
use App\Models\BusinessSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\Installer\EnvWriter;
use App\Services\Installer\InstallerRequirements;
use App\Services\Installer\InstallLock;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class InstallController extends Controller
{
    public function requirements(InstallerRequirements $requirements): View
    {
        return view('install.requirements', [
            'checks' => $requirements->checks(),
            'writableChecks' => $requirements->writableChecks(),
            'envWritable' => $requirements->envFileWritable(),
            'canContinue' => $requirements->passes() && $requirements->pathsWritable() && $requirements->envFileWritable(),
        ]);
    }

    public function showDatabase(InstallerRequirements $requirements): View|RedirectResponse
    {
        if (! $requirements->passes() || ! $requirements->pathsWritable() || ! $requirements->envFileWritable()) {
            return redirect()->route('install.requirements')->with('error', 'Please resolve the requirement checks first.');
        }

        return view('install.database');
    }

    public function storeDatabase(Request $request, EnvWriter $envWriter): RedirectResponse
    {
        $data = $request->validate([
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'integer'],
            'db_database' => ['required', 'string', 'max:255'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ]);

        config(['database.connections.installer_test' => [
            'driver' => 'mysql',
            'host' => $data['db_host'],
            'port' => $data['db_port'],
            'database' => $data['db_database'],
            'username' => $data['db_username'],
            'password' => $data['db_password'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);

        try {
            DB::connection('installer_test')->getPdo();
        } catch (\Throwable $e) {
            DB::purge('installer_test');

            return back()->withInput()->withErrors(['db_host' => 'Could not connect: '.$e->getMessage()]);
        }

        DB::purge('installer_test');

        $envWriter->set([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $data['db_host'],
            'DB_PORT' => (string) $data['db_port'],
            'DB_DATABASE' => $data['db_database'],
            'DB_USERNAME' => $data['db_username'],
            'DB_PASSWORD' => $data['db_password'] ?? '',
        ]);

        config(['database.default' => 'mysql', 'database.connections.mysql' => [
            ...config('database.connections.mysql'),
            'host' => $data['db_host'],
            'port' => $data['db_port'],
            'database' => $data['db_database'],
            'username' => $data['db_username'],
            'password' => $data['db_password'] ?? '',
        ]]);
        DB::purge('mysql');
        DB::setDefaultConnection('mysql');

        return redirect()->route('install.migrate');
    }

    public function showMigrate(): View|RedirectResponse
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            return redirect()->route('install.database')->with('error', 'Configure the database connection first.');
        }

        return view('install.migrate', ['alreadyMigrated' => Schema::hasTable('users')]);
    }

    public function runMigrate(): RedirectResponse
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            return redirect()->route('install.database')->with('error', 'Configure the database connection first.');
        }

        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);

        return redirect()->route('install.owner');
    }

    public function showOwner(): View|RedirectResponse
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('roles')) {
            return redirect()->route('install.migrate')->with('error', 'Run migrations first.');
        }

        if (User::query()->exists()) {
            return redirect()->route('install.business');
        }

        return view('install.owner');
    }

    public function storeOwner(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('roles')) {
            return redirect()->route('install.migrate')->with('error', 'Run migrations first.');
        }

        if (User::query()->exists()) {
            return redirect()->route('install.business');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $ownerRole = Role::where('name', 'owner')->first();

        $owner = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role_id' => $ownerRole?->id,
            'is_active' => true,
        ]);

        // Lets only this browser session continue to the remaining steps
        // now that the database has a user (see EnsureNotInstalled).
        $request->session()->put(EnsureNotInstalled::SESSION_OWNER_KEY, $owner->id);

        return redirect()->route('install.business');
    }

    public function showBusiness(): View|RedirectResponse
    {
        if (! Schema::hasTable('users') || ! User::query()->exists()) {
            return redirect()->route('install.owner')->with('error', 'Create the owner account first.');
        }

        return view('install.business');
    }

    public function storeBusiness(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'max:10'],
            'currency_minor_units' => ['required', 'integer', 'between:0,4'],
            'locale' => ['required', 'in:en,fa,ar'],
            'timezone' => ['required', 'timezone'],
        ]);

        BusinessSetting::current()->update($data);

        return redirect()->route('install.complete');
    }

    public function complete(InstallLock $lock, EnvWriter $envWriter): View|RedirectResponse
    {
        if (! Schema::hasTable('business_settings') || ! BusinessSetting::query()->exists()) {
            return redirect()->route('install.business')->with('error', 'Complete business setup first.');
        }

        if (! $lock->isInstalled()) {
            // The installer only ever runs once for a real business — by the
            // time someone reaches this step they're going live, not still
            // developing locally, so lock down debug mode here rather than
            // relying on a non-technical buyer to know to edit .env by hand
            // (leaving APP_DEBUG on in production leaks stack traces —
            // exactly what CLAUDE.md's security requirements rule out).
            $envWriter->set(['APP_ENV' => 'production', 'APP_DEBUG' => 'false']);
            $lock->lock();
        }

        return view('install.complete');
    }
}
