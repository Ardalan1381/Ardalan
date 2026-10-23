<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('orders', function (?User $user) {
    // Guests hit this endpoint with no authenticated user — must not throw.
    return $user?->hasPermission('orders.view') ?? false;
});
