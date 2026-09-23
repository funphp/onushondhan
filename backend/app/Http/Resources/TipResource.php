<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ContactPreference;
use App\Models\Tip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tip thread. `perspective` controls what is disclosed:
 *  - investigator: sender identity only when the tipster chose not to be anonymous;
 *  - tipster: never any investigator private data.
 */
class TipResource extends JsonResource
{
    public string $perspective = 'investigator';

    public bool $withThread = true;

    public function as(string $perspective, bool $withThread = true): static
    {
        $this->perspective = $perspective;
        $this->withThread = $withThread;

        return $this;
    }

    public function toArray(Request $request): array
    {
        /** @var Tip $tip */
        $tip = $this->resource;
        $forInvestigator = $this->perspective === 'investigator';
        $shareContact = in_array($tip->contact_preference, [ContactPreference::Email, ContactPreference::Phone], true);

        $data = [
            'id' => $tip->public_id,
            'subject' => $tip->subject,
            'status' => $tip->status->value,
            'investigation' => $tip->investigation ? ['id' => $tip->investigation->public_id, 'title' => $tip->investigation->published_title ?? $tip->investigation->title] : null,
            'investigator' => ['public_name' => $tip->investigator->public_name, 'slug' => $tip->investigator->slug],
            'created_at' => $tip->created_at->toIso8601String(),
            'last_message_at' => $tip->last_message_at?->toIso8601String(),
            'unread' => $forInvestigator ? $tip->status->value === 'new' : null,
        ];

        if ($forInvestigator) {
            $data['sender'] = $tip->anonymous_to_investigator || $tip->sender === null
                ? ['anonymous' => true, 'type' => $tip->sender_user_id === null ? 'guest' : 'member', 'username' => null]
                : ['anonymous' => false, 'type' => 'member', 'username' => $tip->sender->username];
            $data['contact_preference'] = $tip->contact_preference->value;
            $data['contact_value'] = $shareContact ? $tip->contact_value : null;
        } else {
            $data['anonymous_to_investigator'] = $tip->anonymous_to_investigator;
            $data['contact_preference'] = $tip->contact_preference->value;
        }

        if (! $this->withThread) {
            return $data;
        }

        $data['message'] = $tip->message;
        $data['attachments'] = $tip->attachments->map(fn ($a) => [
            'id' => $a->public_id,
            'name' => $a->original_name,
            'mime_type' => $a->mime_type,
            'size_bytes' => $a->size_bytes,
            'sha256' => $a->sha256,
            'scan_status' => $a->scan_status->value,
            'download_url' => $forInvestigator || $this->perspective === 'tipster' ? '/api/v1/tip-attachments/'.$a->public_id.'/download' : null,
        ])->values();
        $data['messages'] = $tip->messages->map(fn ($m) => [
            'from' => $m->sender_type->value,
            'body' => $m->body,
            'created_at' => $m->created_at->toIso8601String(),
            'read_at' => $m->read_at?->toIso8601String(),
        ])->values();

        return $data;
    }
}
