<?php

namespace App\Http\Resources\DataCollection;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// One entry of GET /api/v1/forms: a deployed form the user collects for.
class AppFormResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $version = $this->currentVersion;

        return [
            'id'         => $this->id,
            'title'      => $this->name,
            'version'    => $version?->version_number,
            'checksum'   => $version?->checksum,
            'updated_at' => ($version?->deployed_at ?? $this->updated_at)?->toIso8601String(),
        ];
    }
}
