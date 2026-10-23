<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * The full permission catalog, grouped by admin section.
     *
     * @var array<string, array<string, string>>
     */
    private const PERMISSIONS = [
        'dashboard' => [
            'dashboard.view' => 'View dashboard',
        ],
        'orders' => [
            'orders.view' => 'View orders',
            'orders.manage' => 'Update order status, notes and cancellations',
            'orders.create' => 'Build orders on a customer\'s behalf (waiter/POS)',
            'orders.split' => 'Split an order\'s bill into multiple parts',
        ],
        'catalog' => [
            'categories.manage' => 'Manage categories',
            'products.manage' => 'Manage products and options',
            'media.manage' => 'Manage media uploads',
        ],
        'tables' => [
            'tables.manage' => 'Manage table sections and tables',
            'qr.manage' => 'Generate and download QR codes',
        ],
        'waiter_calls' => [
            'waiter_calls.view' => 'View waiter calls',
            'waiter_calls.manage' => 'Resolve waiter calls',
        ],
        'customers' => [
            'customers.view' => 'View customers',
            'customers.manage' => 'Manage customer records',
        ],
        'discounts' => [
            'discounts.view' => 'View discounts and campaigns',
            'discounts.manage' => 'Author discount and campaign rules',
            'discounts.apply' => 'Apply discounts when taking an order',
        ],
        'loyalty' => [
            'loyalty.view' => 'View loyalty rules, rewards and balances',
            'loyalty.manage' => 'Author loyalty rules and rewards',
            'loyalty.redeem' => 'Redeem a customer\'s loyalty points',
        ],
        'inventory' => [
            'inventory.view' => 'View inventory items and stock levels',
            'inventory.manage' => 'Manage inventory items, units and categories',
            'inventory.adjust' => 'Record stock adjustments, waste and counts',
            'inventory.force_negative' => 'Force a stock movement below zero in an emergency',
        ],
        'suppliers' => [
            'suppliers.manage' => 'Manage suppliers',
        ],
        'purchases' => [
            'purchases.view' => 'View purchases',
            'purchases.manage' => 'Create and receive purchases',
        ],
        'shifts' => [
            'shifts.view_own' => 'Open/close own shift and log own cash movements',
            'shifts.view_all' => 'View all staff shifts',
            'shifts.manage_all' => 'Force-close another staff member\'s shift',
        ],
        'invoices' => [
            'invoices.view' => 'View and print invoices',
            'invoices.manage' => 'Generate invoices and manage invoice templates',
        ],
        'printing' => [
            'printers.manage' => 'Configure printers and print routing',
        ],
        'payments' => [
            'payments.view' => 'View payments',
            'payments.manage' => 'Record and manage payments',
            'payments.refund' => 'Refund a payment',
        ],
        'users' => [
            'users.manage' => 'Manage staff accounts',
            'roles.manage' => 'Manage roles and permissions',
        ],
        'reports' => [
            'reports.view' => 'View reports',
            'reports.profit.view' => 'View profit and margin analytics',
        ],
        'system' => [
            'notifications.manage' => 'Manage notifications',
            'audit.view' => 'View audit logs',
            'settings.manage' => 'Manage business and system settings',
        ],
    ];

    /**
     * Permission names granted to each initial role, beyond Owner (which gets all).
     *
     * @var array<string, list<string>>
     */
    private const ROLE_PERMISSIONS = [
        'manager' => [
            'dashboard.view', 'orders.view', 'orders.manage', 'orders.create', 'orders.split',
            'categories.manage', 'products.manage', 'media.manage',
            'tables.manage', 'qr.manage',
            'waiter_calls.view', 'waiter_calls.manage',
            'payments.view', 'payments.manage', 'payments.refund',
            'users.manage',
            'reports.view',
            'notifications.manage', 'audit.view', 'settings.manage',
            'customers.view', 'customers.manage',
            'discounts.view', 'discounts.manage', 'discounts.apply',
            'loyalty.view', 'loyalty.manage', 'loyalty.redeem',
            'inventory.view', 'inventory.manage', 'inventory.adjust',
            'suppliers.manage',
            'purchases.view', 'purchases.manage',
            'shifts.view_own', 'shifts.view_all', 'shifts.manage_all',
            'invoices.view', 'invoices.manage',
            'printers.manage',
        ],
        'cashier' => [
            'dashboard.view', 'orders.view', 'orders.manage', 'orders.create', 'orders.split',
            'payments.view', 'payments.manage',
            'customers.view', 'customers.manage',
            'discounts.view', 'discounts.apply',
            'loyalty.view', 'loyalty.redeem',
            'shifts.view_own',
            'invoices.view',
        ],
        'waiter' => [
            'dashboard.view', 'orders.view', 'orders.manage', 'orders.create', 'orders.split',
            'waiter_calls.view', 'waiter_calls.manage',
            'customers.view', 'customers.manage',
            'discounts.view', 'discounts.apply',
            'loyalty.view', 'loyalty.redeem',
            'shifts.view_own',
            'invoices.view',
        ],
        'kitchen' => [
            'orders.view', 'orders.manage',
        ],
        'barista' => [
            'orders.view', 'orders.manage',
        ],
    ];

    private const ROLES = [
        'owner' => 'Owner',
        'manager' => 'Manager',
        'cashier' => 'Cashier',
        'waiter' => 'Waiter',
        'kitchen' => 'Kitchen',
        'barista' => 'Barista',
    ];

    public function run(): void
    {
        $permissions = [];

        foreach (self::PERMISSIONS as $group => $entries) {
            foreach ($entries as $name => $label) {
                $permissions[$name] = Permission::query()->updateOrCreate(
                    ['name' => $name],
                    ['label' => $label, 'group' => $group],
                );
            }
        }

        foreach (self::ROLES as $name => $label) {
            $role = Role::query()->updateOrCreate(['name' => $name], ['label' => $label]);

            $permissionIds = $name === 'owner'
                ? collect($permissions)->pluck('id')->all()
                : collect(self::ROLE_PERMISSIONS[$name] ?? [])
                    ->map(fn (string $permissionName) => $permissions[$permissionName]->id)
                    ->all();

            $role->permissions()->sync($permissionIds);
        }
    }
}
