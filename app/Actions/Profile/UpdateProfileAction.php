<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\Profile;

class UpdateProfileAction
{
    public function execute(Profile $profile, UpdateProfileRequest $request): Profile
    {
        $profile->update($request->safe()->only(['display_name', 'timezone']));

        return $profile->refresh();
    }
}
