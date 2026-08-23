<?php

namespace App\Services;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Support\Str;

class FriendshipService
{
    public function generateInviteCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (User::where('invite_code', $code)->exists());

        return $code;
    }

    public function sendRequest(User $requester, User $inviter): ?Friendship
    {
        if ($requester->id === $inviter->id) {
            return null;
        }

        $existing = Friendship::where(function ($q) use ($requester, $inviter) {
                $q->where('user_id', $requester->id)->where('friend_id', $inviter->id);
            })
            ->orWhere(function ($q) use ($requester, $inviter) {
                $q->where('user_id', $inviter->id)->where('friend_id', $requester->id);
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        return Friendship::create([
            'user_id' => $requester->id,
            'friend_id' => $inviter->id,
            'status' => 'pending',
        ]);
    }

    public function accept(Friendship $friendship): Friendship
    {
        $friendship->update(['status' => 'accepted']);
        return $friendship->fresh();
    }

    public function decline(Friendship $friendship): Friendship
    {
        $friendship->update(['status' => 'declined']);
        return $friendship->fresh();
    }
}
