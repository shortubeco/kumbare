<?php

namespace App\Domain\Notifications;

use App\Models\Branch;
use App\Models\PanelNotification;
use App\Models\PanelUser;

/** Panel içi bildirimler (push yok). */
class PanelNotifier
{
    public function notifyBranchManager(Branch $branch, string $type, array $data = []): void
    {
        $manager = PanelUser::query()
            ->where('branch_id', $branch->id)
            ->where('role', PanelUser::ROLE_BRANCH_MANAGER)
            ->where('status', 'active')
            ->first();

        if ($manager) {
            $this->notify($manager, $type, $data);
        }
    }

    public function notify(PanelUser $user, string $type, array $data = []): void
    {
        PanelNotification::query()->create([
            'panel_user_id' => $user->id,
            'type' => $type,
            'data' => $data,
        ]);
    }
}
