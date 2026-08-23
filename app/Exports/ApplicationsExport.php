<?php

namespace App\Exports;

use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;

class ApplicationsExport implements FromCollection
{
    public function __construct(
        private User $user
    )
    {
    }

    public function collection(): Collection
    {
        return Application::where('user_id', $this->user->id)->get();
    }
}
