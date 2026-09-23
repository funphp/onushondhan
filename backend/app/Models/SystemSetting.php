<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Non-secret runtime settings. Secrets belong in the environment, never here.
 *
 * @property int $id
 * @property string $key
 * @property mixed $value_json
 * @property string $value_type
 * @property string|null $description
 */
class SystemSetting extends Model
{
    protected $fillable = ['key', 'value_json', 'value_type', 'description'];

    protected function casts(): array
    {
        return ['value_json' => 'json'];
    }
}
